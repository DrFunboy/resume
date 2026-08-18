<article class="card">
    <a href="/article.php?id={$article.id|escape:'url'}" class="card-image-link">
        <img src="{$article.image}" alt="{$article.title|escape}" class="card-image" loading="lazy">
    </a>
    <div class="card-body">
        <h3 class="card-title">
            <a href="/article.php?id={$article.id|escape:'url'}">{$article.title}</a>
        </h3>
        <div class="card-details">
            <p class="card-date">{$article.published_at|date_format_safe:"%d.%m.%Y"}</p>
            <p class="card-views">
                {$article.views}
                <img src="/assets/icons/eye.png" alt="{$article.title|escape}" class="card-icon" loading="lazy">
            </p>
        </div>
        <p class="card-excerpt">{$article.description|truncate:140}</p>
        <a href="/article.php?id={$article.id|escape:'url'}" class="continue-link">Подробнее</a>
    </div>
</article>
