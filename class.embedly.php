<?php

function embed($url)
{
    if (empty($url)) return '';

    // Direct image
    if (preg_match('/https?:\/\/\S+\.(?:png|jpg|jpeg|gif|svg)(\?[^\s]*)?$/i', $url)) {
        $safe = htmlspecialchars($url, ENT_QUOTES);
        return '<a class="loader" href="' . $safe . '" rel="lightbox"><img src="' . $safe . '" alt="" loading="lazy" /></a>';
    }

    // Direct video file
    if (preg_match('/https?:\/\/\S+\.(?:mp4|webm|ogg)(\?[^\s]*)?$/i', $url)) {
        $safe = htmlspecialchars($url, ENT_QUOTES);
        return '<video controls><source src="' . $safe . '"></video>';
    }

    // YouTube
    if (preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $id = $m[1];
        $thumb = 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg';
        $embed = '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $id . '?autoplay=1" frameborder="0" allowfullscreen></iframe>';
        return '<img src="' . $thumb . '" alt="" data-embed="' . htmlspecialchars($embed, ENT_QUOTES) . '" loading="lazy" /><a>&#9654;</a>';
    }

    // Vimeo
    if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
        $id = $m[1];
        $embed = '<iframe src="https://player.vimeo.com/video/' . $id . '?autoplay=1" width="560" height="315" frameborder="0" allowfullscreen></iframe>';
        // No free thumbnail API without HTTP call; show a play-button link instead
        $safe = htmlspecialchars($url, ENT_QUOTES);
        return '<a href="' . $safe . '" class="regular">&#9654; Vimeo</a>';
    }

    // Fallback: plain link
    $safe = htmlspecialchars($url, ENT_QUOTES);
    return '<a href="' . $safe . '" class="regular">' . htmlspecialchars($url) . '</a>';
}
