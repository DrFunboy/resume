{if $total_pages > 1}
<nav class="pagination" aria-label="Pagination">
    {if $page > 1}
        <a class="page-link" href="/category.php?id={$category.id|escape:'url'}&sort={$sort|escape:'url'}&page={$page - 1}">&larr; Назад</a>
    {/if}

    {for $p=1 to $total_pages}
        {if $p == $page}
            <span class="page-link current">{$p}</span>
        {else}
            <a class="page-link" href="/category.php?id={$category.id|escape:'url'}&sort={$sort|escape:'url'}&page={$p}">{$p}</a>
        {/if}
    {/for}

    {if $page < $total_pages}
        <a class="page-link" href="/category.php?id={$category.id|escape:'url'}&sort={$sort|escape:'url'}&page={$page + 1}">Вперед &rarr;</a>
    {/if}
</nav>
{/if}
