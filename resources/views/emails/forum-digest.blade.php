<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Resumo do fórum</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px;">
        <h1 style="margin:0 0 12px;font-size:20px;">Resumo do fórum</h1>
        <p style="margin:0 0 16px;font-size:14px;">Olá, {{ $user->name }}!</p>
        <p style="margin:0 0 16px;font-size:14px;">Estes foram os tópicos com mais movimento recentemente:</p>

        <ul style="margin:0 0 16px;padding-left:20px;font-size:14px;">
            @foreach ($threads as $thread)
                <li style="margin-bottom:10px;">
                    <a href="{{ route('forum.thread', ['categorySlug' => $thread->category?->slug, 'threadSlug' => $thread->slug]) }}"
                       style="color:#4f46e5;font-weight:bold;text-decoration:none;">
                        {{ $thread->title }}
                    </a>
                    <br>
                    <span style="color:#71717a;font-size:12px;">
                        {{ $thread->category?->name }} · {{ $thread->reply_count }} {{ $thread->reply_count === 1 ? 'resposta' : 'respostas' }}
                        · atualizado {{ $thread->last_post_at?->diffForHumans() ?? $thread->created_at->diffForHumans() }}
                    </span>
                </li>
            @endforeach
        </ul>

        <p style="margin:0;font-size:12px;color:#71717a;">
            Você recebe este e-mail porque ativou o resumo do fórum em Editar perfil.
            Desative a opção para deixar de receber.
        </p>
    </div>
</body>
</html>
