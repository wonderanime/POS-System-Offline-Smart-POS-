<?php

function render_pagination(string $baseUrl, int $page, int $totalPages, int $perPage, int $total, array $extra = []): string {
    $qs = fn(array $p) => '?' . http_build_query(array_merge($extra, $p));
    $b  = $baseUrl;

    $html  = '<div class="pag-bar">';
    $html .= '<div class="pag-left"><span>Show</span>';
    $html .= '<select onchange="location.href=\'' . $b . '\'+this.value">';
    foreach ([10,20,25,50,100] as $n) {
        $sel  = $n === $perPage ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($qs(['page'=>1,'per_page'=>$n])) . '"' . $sel . '>' . $n . '</option>';
    }
    $html .= '</select><span>/ page &nbsp;&middot;&nbsp; ' . number_format($total) . ' total</span></div>';

    $html .= '<div class="pag-pages">';
    $html .= $page > 1 ? '<a class="pb" href="' . $b . $qs(['page'=>$page-1,'per_page'=>$perPage]) . '">&lsaquo;</a>' : '<span class="pb dis">&lsaquo;</span>';
    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === $page)                                        $html .= '<span class="pb act">'.$p.'</span>';
        elseif ($p===1||$p===$totalPages||($p>=$page-2&&$p<=$page+2)) $html .= '<a class="pb" href="'.$b.$qs(['page'=>$p,'per_page'=>$perPage]).'">'.$p.'</a>';
        elseif ($p===$page-3||$p===$page+3)                     $html .= '<span class="pb dis">&hellip;</span>';
    }
    $html .= $page < $totalPages ? '<a class="pb" href="' . $b . $qs(['page'=>$page+1,'per_page'=>$perPage]) . '">&rsaquo;</a>' : '<span class="pb dis">&rsaquo;</span>';
    $html .= '</div></div>';
    return $html;
}
