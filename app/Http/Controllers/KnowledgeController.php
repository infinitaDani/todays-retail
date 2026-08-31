<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKnowledgeArticleRequest;
use App\Modules\Knowledge\Models\KnowledgeArticle;
use App\Modules\Knowledge\Models\KnowledgeAssignment;
use App\Modules\Knowledge\Models\KnowledgeTracking;
use App\Tenancy\AuthorizedCoreUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeController extends Controller
{
    public function articles(Request $request): View
    {
        $articles = KnowledgeArticle::query()->withCount('assignments')->when($request->filled('search'), fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->where('title', 'like', '%'.$request->string('search').'%')->orWhere('category', 'like', '%'.$request->string('search').'%')))->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->input('category')))->latest()->paginate(10)->withQueryString();
        return view('tenant.knowledge.articles',['articles'=>$articles,'categories'=>KnowledgeArticle::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),'summary'=>['total'=>KnowledgeArticle::count(),'published'=>KnowledgeArticle::where('status','published')->count(),'draft_or_inactive'=>KnowledgeArticle::whereIn('status',['draft','inactive'])->count(),'pending_reads'=>KnowledgeAssignment::whereDoesntHave('tracking',fn(Builder $query)=>$query->whereNotNull('completed_at'))->count()]]);
    }
    public function create(): View { return view('tenant.knowledge.article-form'); }
    public function store(StoreKnowledgeArticleRequest $request): RedirectResponse { $article=KnowledgeArticle::create($request->validated()); return redirect()->route('knowledge.articles.show',$article)->with('success','Artículo creado correctamente.'); }
    public function show(KnowledgeArticle $article): View { return view('tenant.knowledge.article-show',['article'=>$article,'inUse'=>$this->inUse($article)]); }
    public function edit(KnowledgeArticle $article): View { return view('tenant.knowledge.article-form',compact('article')); }
    public function update(StoreKnowledgeArticleRequest $request, KnowledgeArticle $article): RedirectResponse { $article->update($request->validated()); return redirect()->route('knowledge.articles.show',$article)->with('success','Artículo actualizado correctamente.'); }
    public function publish(KnowledgeArticle $article): RedirectResponse { $article->update(['status'=>'published']); return back()->with('success','Artículo publicado.'); }
    public function deactivate(KnowledgeArticle $article): RedirectResponse { $article->update(['status'=>'inactive']); return back()->with('success','Artículo archivado.'); }
    public function destroy(KnowledgeArticle $article): RedirectResponse { if($this->inUse($article)) return back()->withErrors(['article'=>'Este artículo tiene asignaciones o historial de lectura. Solo puede archivarse.']); $article->delete(); return redirect()->route('knowledge.articles')->with('success','Artículo eliminado.'); }
    public function assign(Request $request, KnowledgeArticle $article, AuthorizedCoreUser $users): RedirectResponse { $data=$request->validate(['core_user_id'=>'required|integer','due_at'=>'nullable|date','required'=>'nullable|boolean']); $users->ensure($request->attributes->get('tenantAccount'),$data['core_user_id']); $assignment=KnowledgeAssignment::create(['article_id'=>$article->id,...$data,'assigned_at'=>now(),'required'=>$request->boolean('required',true)]); KnowledgeTracking::create(['assignment_id'=>$assignment->id]); return back(); }
    public function open(KnowledgeAssignment $assignment): RedirectResponse { $assignment->tracking()->updateOrCreate([],['opened_at'=>now()]); return back(); }
    public function complete(KnowledgeAssignment $assignment): RedirectResponse { $assignment->tracking()->updateOrCreate([],['completed_at'=>now()]); return back(); }
    public function confirm(KnowledgeAssignment $assignment): RedirectResponse { $assignment->tracking()->updateOrCreate([],['confirmed_at'=>now()]); return back(); }
    private function inUse(KnowledgeArticle $article): bool { return $article->assignments()->exists() || KnowledgeTracking::query()->whereHas('assignment',fn(Builder $assignments)=>$assignments->where('article_id',$article->id))->exists(); }
}
