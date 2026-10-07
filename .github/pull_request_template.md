## Resumo

<!-- O que muda e por quê, em 2-4 linhas. -->

## O que entra

<!-- Liste as mudanças visíveis. Ex: -->
<!-- - Botão de coração nos posts com contagem ao vivo -->
<!-- - Página de thread em /posts/{id} -->

## Migrations envolvidas

<!-- Liste os arquivos em database/migrations, ou "Nenhuma". -->
<!-- Rodar pelo painel Admin → Migrations após o deploy. -->

- [ ] Nenhuma
- [ ] Listadas acima (testadas com `php artisan migrate --force` local)

## Como testar

```bash
php artisan test --filter 'NomeDoTeste'
```

<!-- Passos manuais, se houver. Ex: logar, curtir um post, ver o coração acender. -->

1. ...
2. ...

## Checklist

- [ ] Suite verde (`php artisan test`)
- [ ] `vendor/bin/pint --dirty` aplicado apenas nos arquivos tocados
- [ ] Sem credenciais ou dados sensíveis no diff (`.env`, `database.sqlite`)
- [ ] `routes/web.php` sem reformat fora do escopo (pint adora reformatar esse arquivo)
- [ ] CHANGELOG/`changelogs/` atualizado, se visível ao usuário
- [ ] TODO docs atualizados (`Docs/TODO*.md`), se aplicável

## Notas de deploy (hospedagem sem console)

<!-- Marque o que o deploy precisa. Não-commits: public/build vai por FTP. -->
<!-- - [ ] Rodar migrations pelo painel Admin → Migrations -->
<!-- - [ ] Rebuild + upload de public/build (mudança de CSS/JS) -->
<!-- - [ ] Incluir vendor/ (mudança em composer.json/lock) -->
