# Checklist de implantação

## Infraestrutura e segredos

- [ ] PHP 8.4 e MySQL 8.0.16+ homologados; relógios sincronizados por NTP.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS obrigatório e HSTS no proxy.
- [ ] `APP_KEY`, banco, e-mail e integrações armazenados no cofre de segredos.
- [ ] `PULSEIRA_KEY` aleatória, guardada com backup e excluída de qualquer rotação automática.
- [ ] Usuário MySQL criado a partir de `docs/privilegios.sql`, com host restrito à aplicação.
- [ ] `storage/app/private` fora do document root, persistente, criptografado e incluído no backup.
- [ ] Sessões e cache em backend compartilhado; cookies `Secure`, `HttpOnly` e `SameSite=Lax`.

## Publicação

- [ ] `composer install --no-dev --optimize-autoloader` e `npm ci && npm run build` concluídos.
- [ ] `php artisan migrate --force` executado com backup e plano de retorno testado.
- [ ] `php artisan config:cache`, `route:cache` e `view:cache` concluídos.
- [ ] Worker de filas supervisionado; scheduler executando `php artisan schedule:run` a cada minuto.
- [ ] Usuários e senha `Demo@2026` removidos; senha administrativa inicial alterada.
- [ ] Canal dos eventos críticos configurado: alergia, dose divergente, valor crítico, acesso ao portal e integridade.

## Segurança e LGPD

- [ ] Firewall limita MySQL à rede interna e o portal público às rotas necessárias.
- [ ] Rate limits testados a partir do proxy real; IP do cliente chega de proxy confiável.
- [ ] Política de retenção, backup imutável, restauração e descarte aprovada pelo controlador.
- [ ] Consulta de auditoria por paciente validada; logs exportados para armazenamento imutável/SIEM.
- [ ] Quebra de sigilo revisada pela equipe assistencial e pelo encarregado de dados.
- [ ] Teste de restauração preserva prontuário, anexos privados e cadeia de hashes.

## Validação final

- [ ] `php artisan test` verde no schema isolado `prsaude_test`.
- [ ] Cobertura de Actions, Services e Enums ≥ 80%.
- [ ] `php artisan migrate:fresh --seed` validado em ambiente descartável.
- [ ] Fluxos de recepção, triagem, fila, prontuário, medicamentos, exames e portal exercitados.
- [ ] Navegação somente por teclado e contraste AA validados nos temas claro e escuro.
- [ ] Prioridade, alergia, valor crítico e alertas usam texto/ícone além de cor.
- [ ] Monitoramento, alertas, health check, RPO/RTO e contatos de incidente documentados.
