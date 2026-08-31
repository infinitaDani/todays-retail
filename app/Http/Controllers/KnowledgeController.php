<?php

namespace App\Http\Controllers;

use App\Events\KnowledgeArticlePublished;
use App\Core\Users\User;
use App\Http\Requests\StoreKnowledgeArticleRequest;
use App\Http\Requests\StoreKnowledgeCategoryRequest;
use App\Modules\Knowledge\Models\KnowledgeArticle;
use App\Modules\Knowledge\Models\KnowledgeArticleVersion;
use App\Modules\Knowledge\Models\KnowledgeAssignment;
use App\Modules\Knowledge\Models\KnowledgeCategory;
use App\Modules\Knowledge\Models\KnowledgeTracking;
use App\Modules\Knowledge\Models\KnowledgeVersionReading;
use App\Tenancy\AuthorizedCoreUser;
use App\Tenancy\TenantAccountAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgeController extends Controller
{
    public function articles(Request $request): View
    {
        $articles = KnowledgeArticle::query()->with(['categories', 'versions'])->withCount('assignments')
            ->when($request->filled('search'), fn (Builder $q) => $q->where(fn (Builder $n) => $n->where('title', 'like', '%'.$request->string('search').'%')->orWhereHas('categories', fn (Builder $c) => $c->where('name', 'like', '%'.$request->string('search').'%'))))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('category'), fn (Builder $q) => $q->whereHas('categories', fn (Builder $c) => $c->whereKey($request->integer('category'))))
            ->latest()->paginate(10)->withQueryString();

        return view('tenant.knowledge.articles', ['articles' => $articles, 'categories' => $this->categoriesForForm(), 'summary' => [
            'total' => KnowledgeArticle::count(), 'published' => KnowledgeArticle::where('status', 'published')->count(),
            'draft_or_inactive' => KnowledgeArticle::whereIn('status', ['draft', 'inactive'])->count(),
            'pending_reads' => KnowledgeArticleVersion::where('status', 'published')->get()->sum(fn (KnowledgeArticleVersion $version) => $this->pendingReadCount($version, $request)),
        ]]);
    }

    public function create(): View { return view('tenant.knowledge.article-form', ['categories' => $this->categoriesForForm()]); }

    public function store(StoreKnowledgeArticleRequest $request): RedirectResponse
    {
        $article = DB::connection('tenant')->transaction(function () use ($request) {
            $data = $request->validated();
            $article = KnowledgeArticle::create(['title' => $data['title'], 'content' => $data['content'], 'version' => 1, 'status' => 'draft']);
            $article->categories()->sync($data['category_ids'] ?? []);
            $article->versions()->create($this->versionData($data, 1, 'draft', $request->user()->id));
            return $article;
        });
        return redirect()->route('knowledge.articles.show', $article)->with('success', 'Borrador creado correctamente.');
    }

    public function show(KnowledgeArticle $article): View
    {
        $article->load(['categories', 'versions' => fn ($q) => $q->with(['readings' => fn ($readings) => $readings->latest('last_opened_at')])->withCount('readings')->latest('version_number')]);
        $readerIds = $article->versions->flatMap(fn (KnowledgeArticleVersion $version) => $version->readings->pluck('core_user_id'))->unique();
        $readerNames = User::query()->whereIn('id', $readerIds)->pluck('name', 'id');
        return view('tenant.knowledge.article-show', ['article' => $article, 'inUse' => $this->inUse($article), 'readerNames' => $readerNames]);
    }

    public function edit(KnowledgeArticle $article, Request $request): View
    {
        $article->load('categories');
        return view('tenant.knowledge.article-form', ['article' => $article, 'editableVersion' => $this->editableVersion($article, $request->user()->id), 'categories' => $this->categoriesForForm()]);
    }

    public function update(StoreKnowledgeArticleRequest $request, KnowledgeArticle $article): RedirectResponse
    {
        DB::connection('tenant')->transaction(function () use ($request, $article): void {
            $data = $request->validated(); $version = $this->editableVersion($article, $request->user()->id);
            $version->update($this->versionData($data, $version->version_number, 'draft', $request->user()->id));
            $article->categories()->sync($data['category_ids'] ?? []);
            if ($article->status !== 'published') $article->update(['title' => $data['title'], 'content' => $data['content'], 'version' => $version->version_number, 'status' => 'draft']);
        });
        return redirect()->route('knowledge.articles.show', $article)->with('success', 'Borrador guardado. La versión publicada anterior sigue intacta.');
    }

    public function publish(KnowledgeArticle $article, Request $request): RedirectResponse
    {
        $version = DB::connection('tenant')->transaction(function () use ($article, $request) {
            $article = KnowledgeArticle::query()->lockForUpdate()->findOrFail($article->id);
            $version = $this->editableVersion($article, $request->user()->id);
            $article->versions()->where('status', 'published')->lockForUpdate()->update(['status' => 'archived']);
            $version->update(['status' => 'published', 'published_at' => now()]);
            $article->update(['title' => $version->title, 'content' => $version->content, 'version' => $version->version_number, 'status' => 'published']);
            return $version->fresh();
        });
        KnowledgeArticlePublished::dispatch($version);
        return back()->with('success', 'Versión '.$version->version_number.' publicada. Las lecturas quedan pendientes para esta nueva versión.');
    }

    public function deactivate(KnowledgeArticle $article): RedirectResponse { $article->update(['status' => 'inactive']); return back()->with('success', 'Artículo archivado. Su historial se conserva.'); }
    public function destroy(KnowledgeArticle $article): RedirectResponse { if ($this->inUse($article)) return back()->withErrors(['article' => 'Este artículo tiene asignaciones o historial de lectura. Solo puede archivarse.']); $article->delete(); return redirect()->route('knowledge.articles')->with('success', 'Artículo eliminado.'); }

    public function categories(): View { return view('tenant.knowledge.categories', ['categories' => KnowledgeCategory::query()->withCount('articles')->orderBy('sort_order')->orderBy('name')->paginate(15)]); }
    public function createCategory(): View { return view('tenant.knowledge.category-form'); }
    public function storeCategory(StoreKnowledgeCategoryRequest $request): RedirectResponse { $data = $request->validated(); $data['slug'] = $data['slug'] ?: Str::slug($data['name']); KnowledgeCategory::create($data); return redirect()->route('knowledge.categories')->with('success', 'Categoría creada.'); }
    public function editCategory(KnowledgeCategory $category): View { return view('tenant.knowledge.category-form', compact('category')); }
    public function updateCategory(StoreKnowledgeCategoryRequest $request, KnowledgeCategory $category): RedirectResponse { $data = $request->validated(); $data['slug'] = $data['slug'] ?: Str::slug($data['name']); $category->update($data); return redirect()->route('knowledge.categories')->with('success', 'Categoría actualizada.'); }
    public function destroyCategory(KnowledgeCategory $category): RedirectResponse { if ($category->articles()->exists()) return back()->withErrors(['category' => 'No puedes eliminar una categoría que todavía tiene artículos.']); $category->delete(); return back()->with('success', 'Categoría eliminada.'); }

    public function center(Request $request, TenantAccountAccess $access): View
    {
        $account = $request->attributes->get('tenantAccount'); $role = $access->membership($request->user(), $account)?->role?->code; $canPreviewAll = $access->canManageTenant($request->user(), $account);
        $articles = KnowledgeArticle::query()->where('status', 'published')->with(['categories', 'versions' => fn ($q) => $q->where('status', 'published')])
            ->when(! $canPreviewAll, fn (Builder $q) => $q->whereHas('versions', fn (Builder $v) => $v->where('status', 'published')->where(fn (Builder $a) => $a->whereNull('audience')->orWhereJsonContains('audience', 'all')->orWhereJsonContains('audience', $role))))->get();
        $categories = KnowledgeCategory::query()->where('is_active', true)->with(['articles' => fn ($q) => $q->whereIn('knowledge_articles.id', $articles->pluck('id'))])->orderBy('sort_order')->orderBy('name')->get();
        return view('tenant.knowledge.center', compact('articles', 'categories', 'canPreviewAll'));
    }

    public function read(Request $request, KnowledgeArticle $article, TenantAccountAccess $access): View
    {
        $account = $request->attributes->get('tenantAccount'); $role = $access->membership($request->user(), $account)?->role?->code; $isManager = $access->canManageTenant($request->user(), $account);
        abort_unless($article->status === 'published', 404); $version = $article->versions()->where('status', 'published')->latest('version_number')->firstOrFail(); abort_unless($isManager || $this->visibleTo($version, $role), 403);
        $reading = $this->readingFor($version, $request->user()->id, true);
        $reading->update(['first_opened_at' => $reading->first_opened_at ?: now(), 'last_opened_at' => now(), 'last_heartbeat_at' => now()]); $article->load('categories');
        return view('tenant.knowledge.read', compact('article', 'version', 'reading', 'isManager'));
    }

    public function heartbeat(Request $request, KnowledgeArticleVersion $version): JsonResponse { $this->ensureReadableVersion($request, $version); $claimed = $request->validate(['seconds' => ['required', 'integer', 'min:1', 'max:120']])['seconds']; $now = now(); $reading = DB::connection('tenant')->transaction(function () use ($version, $request, $now, $claimed) { $reading = KnowledgeVersionReading::query()->where(['knowledge_article_version_id' => $version->id, 'core_user_id' => $request->user()->id])->lockForUpdate()->first() ?? $this->readingFor($version, $request->user()->id, false); $elapsed = $reading->last_heartbeat_at ? max(0, (int) $reading->last_heartbeat_at->diffInSeconds($now)) : 0; $credited = (int) min($claimed, $elapsed, 120); $reading->update(['active_seconds' => $reading->active_seconds + $credited, 'last_opened_at' => $now, 'last_heartbeat_at' => $now]); return $credited; }); return response()->json(['ok' => true, 'credited_seconds' => $reading]); }
    public function confirmVersion(Request $request, KnowledgeArticleVersion $version): RedirectResponse { $this->ensureReadableVersion($request, $version); abort_unless($version->requires_confirmation, 422, 'Esta versión no requiere confirmación.'); DB::connection('tenant')->transaction(function () use ($version, $request) { $reading = KnowledgeVersionReading::query()->where(['knowledge_article_version_id' => $version->id, 'core_user_id' => $request->user()->id])->lockForUpdate()->first() ?? $this->readingFor($version, $request->user()->id, false); if (! $reading->confirmed_at) $reading->update(['confirmed_at' => now(), 'last_opened_at' => now()]); }); return back()->with('success', 'Lectura confirmada.'); }

    // Retained for legacy integrations; the new reading state is version-based.
    public function assign(Request $request, KnowledgeArticle $article, AuthorizedCoreUser $users): RedirectResponse { $data=$request->validate(['core_user_id'=>'required|integer','due_at'=>'nullable|date','required'=>'nullable|boolean']); $users->ensure($request->attributes->get('tenantAccount'),$data['core_user_id']); $assignment=KnowledgeAssignment::create(['article_id'=>$article->id,...$data,'assigned_at'=>now(),'required'=>$request->boolean('required',true)]); KnowledgeTracking::create(['assignment_id'=>$assignment->id]); return back(); }
    public function open(KnowledgeAssignment $assignment): RedirectResponse { $assignment->tracking()->updateOrCreate([],['opened_at'=>now()]); return back(); }
    public function complete(KnowledgeAssignment $assignment): RedirectResponse { $assignment->tracking()->updateOrCreate([],['completed_at'=>now()]); return back(); }
    public function confirm(KnowledgeAssignment $assignment): RedirectResponse { $assignment->tracking()->updateOrCreate([],['confirmed_at'=>now()]); return back(); }

    private function categoriesForForm() { return KnowledgeCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(); }
    private function versionData(array $data, int $number, string $status, int $author): array { return ['version_number'=>$number, 'title'=>$data['title'], 'content'=>$data['content'], 'author_core_user_id'=>$author, 'status'=>$status, 'requires_confirmation'=>(bool)($data['requires_confirmation']??false), 'audience'=>array_values(array_unique($data['audience']??['all']))]; }
    private function editableVersion(KnowledgeArticle $article, int $author): KnowledgeArticleVersion { if ($draft=$article->versions()->where('status','draft')->latest('version_number')->first()) return $draft; $source=$article->versions()->where('status','published')->latest('version_number')->first(); $number=($article->versions()->max('version_number')??0)+1; return $article->versions()->create(['version_number'=>$number,'title'=>$source?->title??$article->title,'content'=>$source?->content??$article->content,'author_core_user_id'=>$author,'status'=>'draft','requires_confirmation'=>$source?->requires_confirmation??false,'audience'=>$source?->audience??['all']]); }
    private function inUse(KnowledgeArticle $article): bool { return $article->assignments()->exists() || $article->versions()->whereHas('readings')->exists() || KnowledgeTracking::query()->whereHas('assignment',fn(Builder $q)=>$q->where('article_id',$article->id))->exists(); }
    private function visibleTo(KnowledgeArticleVersion $version, ?string $role): bool { $audience=$version->audience?:['all']; return in_array('all',$audience,true)||in_array($role,$audience,true); }
    private function ensureReadableVersion(Request $request, KnowledgeArticleVersion $version): void { $article=$version->article; abort_unless($article&&$article->status==='published'&&$version->status==='published',404); $access=app(TenantAccountAccess::class); $account=$request->attributes->get('tenantAccount'); $role=$access->membership($request->user(),$account)?->role?->code; abort_unless($access->canManageTenant($request->user(),$account)||$this->visibleTo($version,$role),403); }
    private function readingFor(KnowledgeArticleVersion $version, int $userId, bool $opened): KnowledgeVersionReading { $attributes=['knowledge_article_version_id'=>$version->id,'core_user_id'=>$userId]; $values=['first_opened_at'=>$opened?now():null,'last_opened_at'=>$opened?now():null,'last_heartbeat_at'=>now()]; try { return KnowledgeVersionReading::firstOrCreate($attributes, $values); } catch (QueryException $exception) { if (! str_contains(strtolower($exception->getMessage()), 'duplicate')) throw $exception; return KnowledgeVersionReading::query()->where($attributes)->lockForUpdate()->firstOrFail(); } }
    private function pendingReadCount(KnowledgeArticleVersion $version, Request $request): int { $eligible = $this->eligibleUserIds($version, $request); return max(0, count($eligible) - $version->readings()->whereNotNull('confirmed_at')->whereIn('core_user_id', $eligible)->count()); }
    private function eligibleUserIds(KnowledgeArticleVersion $version, Request $request): array { $account = $request->attributes->get('tenantAccount'); if (! $account) return []; $audience = $version->audience ?: ['all']; return $account->memberships()->with('role')->get()->filter(fn ($membership) => in_array('all', $audience, true) || in_array($membership->role?->code, $audience, true))->pluck('user_id')->all(); }
}
