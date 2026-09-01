<x-layouts.tenant
    title="Knowledge Center"
    subtitle="Guías y recursos para tu operación"
>
    @if ($canPreviewAll)
        <div class="alert alert-info d-flex justify-content-between align-items-center">
            <span>
                Estás viendo la experiencia de colaboradoras como administrador.
            </span>

            <a
                class="btn btn-sm btn-outline-primary"
                href="{{ route('knowledge.articles') }}"
            >
                Volver al backoffice
            </a>
        </div>
    @endif

    <div class="tr-card mb-4">
        <div class="input-group input-group-lg">
            <span class="input-group-text bg-transparent">
                <i data-lucide="search"></i>
            </span>

            <input
                class="form-control"
                id="knowledge-search"
                placeholder="Busca guías, procesos o respuestas..."
            >
        </div>
    </div>

    <div class="row g-3">
        @forelse ($categories as $category)
            <div
                class="col-md-6 col-xl-4 knowledge-category"
                data-search="{{ strtolower($category->name . ' ' . $category->description) }}"
            >
                <div class="tr-card h-100">
                    <div class="d-flex gap-3">
                        <span class="summary-icon bg-primary-subtle text-primary">
                            <i data-lucide="{{ $category->icon ?: 'folder-open' }}"></i>
                        </span>

                        <div>
                            <h5>
                                {{ $category->name }}
                            </h5>

                            <p class="text-muted mb-2">
                                {{ $category->description }}
                            </p>

                            <span class="badge badge-soft-primary">
                                {{ $category->articles->count() }} artículo(s)
                            </span>
                        </div>
                    </div>

                    <hr>

                    @forelse ($category->articles as $article)
                        <a
                            class="d-flex justify-content-between align-items-center py-2 text-decoration-none knowledge-article-link"
                            data-search="{{ strtolower($article->title) }}"
                            href="{{ route('knowledge.read', $article) }}"
                        >
                            <span>
                                {{ $article->title }}
                            </span>

                            <i
                                data-lucide="chevron-right"
                                class="icon-xs"
                            ></i>
                        </a>
                    @empty
                        <p class="text-muted mb-0">
                            No hay artículos disponibles.
                        </p>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="listing-empty">
                    No hay contenido publicado disponible para ti.
                </div>
            </div>
        @endforelse
    </div>

    @push('page-scripts')
        <script>
            document
                .getElementById('knowledge-search')
                ?.addEventListener('input', function () {
                    const query = this.value.toLowerCase();

                    document
                        .querySelectorAll('.knowledge-category')
                        .forEach((category) => {
                            const categoryMatches =
                                category.dataset.search.includes(query);

                            const articleMatches = [
                                ...category.querySelectorAll(
                                    '.knowledge-article-link'
                                ),
                            ].some((article) =>
                                article.dataset.search.includes(query)
                            );

                            category.hidden =
                                query &&
                                !categoryMatches &&
                                !articleMatches;
                        });
                });
        </script>
    @endpush
</x-layouts.tenant>