# 01 — Domínio, DNS, HTTPS e e-mail

## Situação verificada em 11/11/2026 (consultas públicas RDAP/DNS)

| Domínio | Situação | Observações |
| --- | --- | --- |
| `mknegocios.com` | **Registrado** em 11/05/2026, expira em 11/05/2027 (Squarespace Domains) | DNS na Squarespace; página "Em breve" no ar; `www` → Squarespace. E-mail no **Google Workspace** (MX `smtp.google.com`, SPF e DKIM configurados). **Sem DMARC.** |
| `mknegocios.com.br` | **Sem registro** no Registro.br (RDAP 404) | Aparentemente disponível. Só é garantido após concluir o registro em registro.br (exige CPF/CNPJ). |

> Bloqueio: confirmar que `mknegocios.com` pertence à MK Negócios. Os
> registros de e-mail sugerem que sim (o endereço `contato@mknegocios.com`
> do WIL-13 usa esse domínio), mas o dado do titular não é público.

## Recomendação de domínio

1. **Registrar `mknegocios.com.br` agora** (proteção da marca; custo anual
   baixo). Ativar renovação automática nos dois domínios — o `.com` expira em
   maio/2027.
2. **Escolher um único domínio canônico** para o site e redirecionar o outro
   com **301** (incluindo `www`). Opções:
   - **`mknegocios.com.br` canônico (recomendado):** público é local,
     `.com.br` transmite confiança para quem busca imóvel/contador no Litoral
     Norte e é o que o WIL-15 cita. O e-mail pode continuar em
     `@mknegocios.com` sem prejuízo.
   - `mknegocios.com` canônico: zero migração, mesmo domínio do e-mail.
   - A decisão precisa ser tomada **antes** do lançamento; trocar depois
     custa reindexação.
3. Usar a versão **sem `www`** como canônica (ou com, mas uma só) e
   redirecionar a outra com 301.

## Separação site × MK Gestão (WIL-16)

| Host | Aplicação | Indexação |
| --- | --- | --- |
| `mknegocios.com.br` (canônico) | Site público | Indexável |
| `www.` e domínio alternativo | Redirect 301 para o canônico | — |
| `app.mknegocios.com.br` | MK Gestão (sistema interno) | **Não indexar** |
| `staging.` / homologação | Qualquer ambiente de teste | **Não indexar** + idealmente protegido por senha |

Requisitos para o MK Gestão (`app.`):

- Responder com cabeçalho `X-Robots-Tag: noindex, nofollow` em todas as
  respostas.
- `robots.txt` do app com `Disallow: /`. Hoje `public/robots.txt` deste
  repositório permite tudo (`Disallow:` vazio) — ajustar na tarefa de deploy
  (WIL-10). Observação: `robots.txt` sozinho não remove páginas do índice; o
  `X-Robots-Tag` é o que garante.
- Não linkar o app a partir do conteúdo do site, exceto um link discreto
  "Área do cliente" no rodapé com `rel="nofollow"`, se desejado.

## DNS

- Ao sair da Squarespace, **preservar os registros de e-mail** (MX, SPF,
  DKIM `google._domainkey`) — perder esses registros derruba o e-mail.
- Adicionar **DMARC** ao `mknegocios.com` (começar em monitoramento):
  `_dmarc.mknegocios.com TXT "v=DMARC1; p=none; rua=mailto:contato@mknegocios.com"`
  e, após algumas semanas sem falhas, subir para `p=quarantine`.
- Se o formulário enviar e-mail por um serviço transacional (Resend,
  Postmark, SES etc.), incluir o SPF/DKIM desse serviço no domínio remetente.
- Registro TXT de verificação do **Google Search Console** (propriedade de
  domínio) em cada domínio.

## HTTPS

- Certificado válido para o canônico, `www` e `app` (Let's Encrypt ou o da
  hospedagem).
- Redirecionar todo `http://` para `https://` em um único salto (sem cadeias
  http → www → sem-www).
- Ativar HSTS (`Strict-Transport-Security: max-age=31536000`) depois que o
  HTTPS estiver estável; não usar `preload` no primeiro momento.

## Hospedagem / deploy

- Deploy do site **independente** do MK Gestão (repositório ou app separado,
  banco separado — WIL-16). Uma queda ou deploy do app não deve derrubar o
  site.
- Ambiente de homologação com `noindex` desde o primeiro dia (é comum o
  Google indexar staging esquecido).
