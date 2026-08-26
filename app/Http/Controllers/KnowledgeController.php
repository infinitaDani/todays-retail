<?php

namespace App\Http\Controllers;

use App\Modules\Knowledge\Models\KnowledgeArticle;
use App\Modules\Knowledge\Models\KnowledgeAssignment;
use App\Modules\Knowledge\Models\KnowledgeTracking;
use App\Tenancy\AuthorizedCoreUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function articles(Request $request)
    {
        $articles = KnowledgeArticle::query()
            ->withCount('assignments')
            ->when($request->filled('search'), fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->where('title', 'like', '%'.$request->string('search').'%')->orWhere('category', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->input('category')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('tenant.knowledge.articles', [
            'articles' => $articles,
            'users' => $request->attributes->get('tenantAccount')->users()->where('users.status', 'active')->get(),
            'categories' => KnowledgeArticle::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'summary' => [
                'total' => KnowledgeArticle::count(),
                'published' => KnowledgeArticle::where('status', 'published')->count(),
                'draft_or_inactive' => KnowledgeArticle::whereIn('status', ['draft', 'inactive'])->count(),
                'pending_reads' => KnowledgeAssignment::whereDoesntHave('tracking', fn (Builder $query) => $query->whereNotNull('completed_at'))->count(),
            ],
        ]);
    }

    public function storeArticle(Request $request)
    {
        KnowledgeArticle::create($request->validate(['title' => 'required|max:200', 'content' => 'required', 'category' => 'nullable|max:100', 'version' => 'required|integer|min:1', 'status' => 'required|in:draft,published,inactive']));

        return back();
    }

    public function assign(Request $request, KnowledgeArticle $article, AuthorizedCoreUser $users)
    {
        $data = $request->validate(['core_user_id' => 'required|integer', 'due_at' => 'nullable|date', 'required' => 'nullable|boolean']);
        $users->ensure($request->attributes->get('tenantAccount'), $data['core_user_id']);
        $assignment = KnowledgeAssignment::create(['article_id' => $article->id, ...$data, 'assigned_at' => now(), 'required' => $request->boolean('required', true)]);
        KnowledgeTracking::create(['assignment_id' => $assignment->id]);

        return back();
    }

    public function open(KnowledgeAssignment $assignment) { $assignment->tracking()->updateOrCreate([], ['opened_at' => now()]); return back(); }
    public function complete(KnowledgeAssignment $assignment) { $assignment->tracking()->updateOrCreate([], ['completed_at' => now()]); return back(); }
    public function confirm(KnowledgeAssignment $assignment) { $assignment->tracking()->updateOrCreate([], ['confirmed_at' => now()]); return back(); }
}
