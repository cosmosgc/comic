<?php

/**
 * Gamification tuning: experience per interaction, the level curve,
 * streak bonuses and the badge catalogue.
 *
 * Agents/admins can retune these numbers without touching code. The
 * `exp` keys match the action names in App\Models\ActivityLog::actions()
 * so awarding happens automatically from the activity feed.
 */
return [

    // Master switch — flip to false to disable all exp/badge writes.
    'enabled' => (bool) env('GAMIFICATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Experience per action
    |--------------------------------------------------------------------------
    | Creating content pays the most; passive engagement pays a little.
    | Only positive actions are listed (unliking grants nothing).
    */
    'exp' => [
        'comic.uploaded' => 100,
        'post.created' => 25,
        'post.replied' => 15,
        'comic.commented' => 10,
        'collection.created' => 10,
        'auth.login' => 5,
        'comic.liked' => 3,
        'post.liked' => 3,
        'collection.favorited' => 3,
        'collection.added' => 2,
        'search.comics' => 1,
        'search.posts' => 1,
        'forum.thread.created' => 20,
        'forum.post.replied' => 8,
        'forum.post.reacted' => 2,
        'forum.thread.solved' => 10,
        'marketplace.listing.created' => 15,
        'marketplace.listing.sold' => 30,
        'marketplace.inquiry.sent' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-farming
    |--------------------------------------------------------------------------
    | These actions pay only the first time a user performs them on a
    | given subject, so like/unlike/re-like cannot be milked for exp.
    */
    'unique_subject_actions' => [
        'comic.liked',
        'post.liked',
        'collection.favorited',
        'forum.post.reacted',
        'forum.thread.solved',
    ],

    /*
    |--------------------------------------------------------------------------
    | Level curve
    |--------------------------------------------------------------------------
    | `level_curve` shapes how the cost grows and `level_base` scales it.
    | Total experience to reach level L (n = L-1):
    |   linear      → base * n                 (100, 200, 300, 400 …)
    |   triangular  → base * n * (n+1) / 2     (100, 300, 600, 1000 …)
    |   quadratic   → base * n²                (100, 400, 900, 1600 …)
    |   exponential → base * (2ⁿ − 1)          (100, 300, 700, 1500 …)
    | Editable in Admin → Gamificação, which previews the curve.
    */
    'level_base' => 100,

    'level_curve' => 'triangular',

    /*
    |--------------------------------------------------------------------------
    | Notifications & profile showcase
    |--------------------------------------------------------------------------
    */
    'notify' => true,
    'showcase_limit' => 3,

    /*
    |--------------------------------------------------------------------------
    | Streaks
    |--------------------------------------------------------------------------
    | Any activity on a new day extends the streak; the bonus is
    | streak_bonus * current_streak (awarded once per day).
    */
    'streak_bonus' => 5,

    /*
    |--------------------------------------------------------------------------
    | Badges
    |--------------------------------------------------------------------------
    | `metric` is resolved by App\Services\Gamification::metric() and
    | compared against `threshold`. Order is display order.
    */
    'badges' => [
        'newcomer' => [
            'label' => 'Novato',
            'description' => 'Deu os primeiros passos no site.',
            'icon' => '🌱',
            'metric' => 'experience',
            'threshold' => 1,
        ],
        'first_comic' => [
            'label' => 'Primeiro Comic',
            'description' => 'Publicou seu primeiro comic.',
            'icon' => '📚',
            'metric' => 'comics_count',
            'threshold' => 1,
        ],
        'comic_master' => [
            'label' => 'Mestre dos Comics',
            'description' => 'Publicou 25 comics.',
            'icon' => '🏆',
            'metric' => 'comics_count',
            'threshold' => 25,
        ],
        'first_post' => [
            'label' => 'Primeira Palavra',
            'description' => 'Criou seu primeiro post.',
            'icon' => '💬',
            'metric' => 'posts_count',
            'threshold' => 1,
        ],
        'prolific_writer' => [
            'label' => 'Prolífico',
            'description' => 'Criou 50 posts.',
            'icon' => '✍️',
            'metric' => 'posts_count',
            'threshold' => 50,
        ],
        'commenter' => [
            'label' => 'Comentarista',
            'description' => 'Comentou 5 vezes em comics.',
            'icon' => '💭',
            'metric' => 'comments_count',
            'threshold' => 5,
        ],
        'social' => [
            'label' => 'Sociável',
            'description' => 'Curtiu 25 conteúdos.',
            'icon' => '❤️',
            'metric' => 'likes_given',
            'threshold' => 25,
        ],
        'collector' => [
            'label' => 'Colecionador',
            'description' => 'Criou 5 coleções.',
            'icon' => '🗂️',
            'metric' => 'collections_count',
            'threshold' => 5,
        ],
        'explorer' => [
            'label' => 'Explorador',
            'description' => 'Fez 50 buscas.',
            'icon' => '🔎',
            'metric' => 'searches_count',
            'threshold' => 50,
        ],
        'regular' => [
            'label' => 'Frequentador',
            'description' => 'Manteve uma sequência de 7 dias.',
            'icon' => '🗓️',
            'metric' => 'longest_streak',
            'threshold' => 7,
        ],
        'veteran' => [
            'label' => 'Veterano',
            'description' => 'Alcançou o nível 10.',
            'icon' => '🎖️',
            'metric' => 'level',
            'threshold' => 10,
        ],
        'first_thread' => [
            'label' => 'Primeiro Tópico',
            'description' => 'Criou seu primeiro tópico no fórum.',
            'icon' => '🧵',
            'metric' => 'forum_threads_count',
            'threshold' => 1,
        ],
        'conversationalist' => [
            'label' => 'Conversador',
            'description' => 'Publicou 100 mensagens no fórum.',
            'icon' => '🗣️',
            'metric' => 'forum_posts_count',
            'threshold' => 100,
        ],
        'helpful' => [
            'label' => 'Prestativo',
            'description' => 'Teve 10 respostas aceitas.',
            'icon' => '✅',
            'metric' => 'forum_solved_count',
            'threshold' => 10,
        ],
        'moderator' => [
            'label' => 'Moderador',
            'description' => 'Atua na moderação do fórum.',
            'icon' => '🛡️',
            'metric' => 'forum_moderations_count',
            'threshold' => 1,
        ],
        'legendary' => [
            'label' => 'Lendário',
            'description' => 'Alcançou o nível 25.',
            'icon' => '👑',
            'metric' => 'level',
            'threshold' => 25,
        ],
    ],
];
