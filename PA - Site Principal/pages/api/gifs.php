<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$gifs = [
    'https://media.giphy.com/media/g9GhZuyYxChDi/giphy.gif',
    'https://media.giphy.com/media/3o85xIO33l7RlmLR4I/giphy.gif',
    'https://media.giphy.com/media/26uf1EUQzrGMzjK2c/giphy.gif',
    'https://media.giphy.com/media/3o6Zt6KHxJTbXCPAXe/giphy.gif',
    
    'https://media.giphy.com/media/4Z9g0DdoJPAKc/giphy.gif',
    'https://media.giphy.com/media/xT9IgEx8SbQ0teblYA/giphy.gif',
    'https://media.giphy.com/media/JIX9RW7Ks6KSQ/giphy.gif',
    'https://media.giphy.com/media/l0HlQ7LRalQqdWfao/giphy.gif',
    
    'https://media.giphy.com/media/3o6ZtpWz2j7kyrsJna/giphy.gif',
    'https://media.giphy.com/media/l0MYt5jPR6QX5pnqM/giphy.gif',
    'https://media.giphy.com/media/3o85xIO33l7RlmLR4I/giphy.gif',
    'https://media.giphy.com/media/Qvd0CTiVwH7gU/giphy.gif',
    
    'https://media.giphy.com/media/Yl5aO3c81dJ3y/giphy.gif',
    'https://media.giphy.com/media/l0HlNaQ9nWINQG5Es/giphy.gif',
    'https://media.giphy.com/media/l4FatPUShagvnxsQw/giphy.gif',
    'https://media.giphy.com/media/26tknCqiJrUzbzjN2/giphy.gif',
    
    'https://media.giphy.com/media/12XMGIQvQDBw8E/giphy.gif',
    'https://media.giphy.com/media/l0HlQy9x8FZo0XO1i/giphy.gif',
    'https://media.giphy.com/media/kRmg8zeReRDL1 Nicholas/giphy.gif',
    'https://media.giphy.com/media/l3q2K5jin38mO80Zo/giphy.gif',
    
    'https://media.giphy.com/media/3o7TKU2On2sgWtGlAI/giphy.gif',
    'https://media.giphy.com/media/l0HlHZfEgG6cOBJN6/giphy.gif',
];

$query = $_GET['q'] ?? '';

if ($query) {
    $filtered = array_filter($gifs, function() { return rand(0, 1); });
    $result = array_values($filtered);
} else {
    $result = $gifs;
    shuffle($result);
    $result = array_slice($result, 0, 20);
}

echo json_encode([
    'success' => true,
    'gifs' => $result,
    'total' => count($result)
]);
