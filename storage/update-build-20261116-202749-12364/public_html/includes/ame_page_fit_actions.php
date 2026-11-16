<?php
/**
 * Page-fit API helpers — used by modules/settings/actions/page_layout.php
 * and ame-page-fit.js (per-user layout JSON in ame_page_layouts).
 */
declare(strict_types=1);

if (!function_exists('ame_page_fit_items_for_path')) {
    /**
     * Server-side default layout seeds per page path (empty = client collects on save).
     *
     * @return list<array{path:string,x:float,y:float,w:float,h:float}>
     */
    function ame_page_fit_items_for_path(string $path): array
    {
        $path = explode('?', $path, 2)[0];
        if ($path === '' || !str_starts_with($path, '/')) {
            return [];
        }

        /** @var array<string, list<array{path:string,x:float,y:float,w:float,h:float}>> $catalog */
        static $catalog = [
            '/modules/settings/tools.php' => [],
            '/modules/settings/easy_update.php' => [],
        ];

        return $catalog[$path] ?? [];
    }
}