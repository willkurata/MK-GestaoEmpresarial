# 07 — Critérios de aceite e testes antes do lançamento

## Testes automatizados sugeridos (implementação)

- [ ] Página de imóvel publicada responde 200 e o HTML **do servidor**
      contém `<title>`, `meta description`, `canonical`, `og:title`,
      `og:image` (capa) e o código de referência.
- [ ] Imóvel em rascunho responde 404 ao público.
- [ ] Imóvel vendido/alugado responde 200 com `noindex` e selo de status.
- [ ] Slug antigo de imóvel redireciona com 301 para o atual.
- [ ] `/sitemap.xml` lista apenas imóveis publicados e disponíveis; não
      lista rascunhos nem vendidos; `lastmod` = `updated_at`.
- [ ] Rotas do painel e do MK Gestão retornam `X-Robots-Tag: noindex`.
- [ ] Link de WhatsApp contém `5512997298895`, o `REF` e texto codificado.
- [ ] Formulário: consentimento obrigatório; honeypot; limite de taxa
      (429); grava referência, página de origem e versão do consentimento;
      notificação enviada.

## Checklist manual de lançamento

**Domínio e infra**
- [ ] Domínio canônico decidido; alternativos e `www` → 301 em um salto.
- [ ] HTTPS válido em todos os hosts; `http` → `https`.
- [ ] MX/SPF/DKIM preservados; DMARC criado; e-mail de teste enviado e
      recebido.
- [ ] Homologação e `app.` com `noindex`.

**SEO**
- [ ] Nenhuma página com `noindex` acidental em produção (conferir layout).
- [ ] `robots.txt` aponta o sitemap e não bloqueia CSS/JS.
- [ ] Search Console (propriedade de domínio) verificado; sitemap enviado;
      "Inspeção de URL" na Início e em um imóvel sem erros.
- [ ] Bing Webmaster Tools importado do Search Console.
- [ ] JSON-LD sem erros no Schema Markup Validator.
- [ ] PageSpeed (mobile): LCP < 2,5 s, CLS < 0,1, SEO 100.
- [ ] Nenhum texto `[A CONFIRMAR]` visível no site.

**Compartilhamento**
- [ ] Link de imóvel colado no WhatsApp mostra foto, título e descrição.
- [ ] Mesmo teste no
      [Sharing Debugger do Facebook](https://developers.facebook.com/tools/debug/).

**Conversão e analytics**
- [ ] Botões de WhatsApp abrem a conversa com a mensagem certa no celular
      (Android e iPhone) e no computador (WhatsApp Web).
- [ ] Formulário enviado chega ao e-mail e ao banco.
- [ ] GA4 DebugView mostra `whatsapp_click` e `generate_lead` com
      parâmetros; ambos marcados como eventos-chave.
- [ ] Recusar cookies → nenhum cookie `_ga` gravado.

**Local**
- [ ] Perfil da Empresa no Google verificado, com link para o site (UTM).
- [ ] NAP idêntico no site, Perfil da Empresa e redes sociais.
- [ ] CRECI e CRC exibidos.
