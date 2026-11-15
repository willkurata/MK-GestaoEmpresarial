# 02 — SEO técnico

## Requisito crítico: HTML renderizado no servidor

O MK Gestão usa Inertia + Vue (renderização no navegador). Se o site público
usar a mesma stack, **as páginas públicas precisam de SSR** (Inertia SSR) ou
Blade. Motivo:

- Os robôs do **WhatsApp, Facebook e LinkedIn não executam JavaScript**. Sem
  `og:*` no HTML inicial, o link do imóvel compartilhado no WhatsApp aparece
  sem foto e sem título — e o WhatsApp é o principal canal de divulgação.
- O Google renderiza JS, mas com atraso e menor confiabilidade.

Teste: `curl -s https://<dominio>/imoveis/<slug> | grep og:image` deve
retornar a tag, sem depender de JS.

## Estrutura de URLs

URLs em minúsculas, com hífen, sem acentos, sem barra final.

```
/                                     Início
/imoveis                              Listagem geral (filtros via query string)
/imoveis/ubatuba                      Imóveis em Ubatuba
/imoveis/caraguatatuba                Imóveis em Caraguatatuba
/imoveis/{ref}-{slug}                 Página do imóvel
                                      ex.: /imoveis/mk0123-casa-3-quartos-itagua-ubatuba
/contabilidade                        Visão geral dos serviços contábeis
/contabilidade/contabilidade-geral
/contabilidade/abertura-e-regularizacao-de-empresas
/contabilidade/baixa-de-empresas
/contabilidade/assessoria-contabil
/contabilidade/contabilidade-para-mei
/empresas                             (escopo a definir — ver bloqueios)
/sobre
/contato
/privacidade
/sitemap.xml
/robots.txt
```

Regras:

- **Imóvel:** o código de referência (`mk0123`) vem primeiro e é o
  identificador estável. Se o título/slug mudar, a rota resolve pelo código
  e redireciona com **301** para o slug atual.
- **Filtros** (`/imoveis?tipo=casa&quartos=3`) usam `<link rel="canonical">`
  apontando para a página sem parâmetros (ou para a página de cidade
  correspondente). Não colocar URLs com filtros no sitemap.
- **Paginação** (`?pagina=2`): cada página com canonical para si mesma;
  links `<a href>` reais entre páginas (não só botão "carregar mais" em JS).
- Páginas por cidade de contabilidade (`/contabilidade/ubatuba`) **só se
  houver conteúdo realmente diferente** por cidade. Páginas idênticas com o
  nome da cidade trocado são tratadas pelo Google como "doorway pages" e
  podem prejudicar o site. Na V1, mencionar as duas cidades nas páginas de
  serviço já é suficiente.

## Ciclo de vida do imóvel e SEO

| Estado (WIL-17) | HTTP | Indexação | Sitemap |
| --- | --- | --- | --- |
| Rascunho | 404 para o público | — | Não |
| Publicado / disponível | 200 | `index` | Sim |
| Vendido / Alugado | 200 com selo "Vendido"/"Alugado" + imóveis semelhantes | `noindex` | Não |
| Removido (excluído) | 410 (ou 301 para a listagem da cidade) | — | Não |

Manter vendidos no ar por um período (ex.: 60–90 dias) preserva links já
compartilhados no WhatsApp e mostra histórico de negócios fechados.

## Metatags por página

Obrigatórias em todas as páginas públicas:

```html
<html lang="pt-BR">
<title>…</title>                         <!-- até ~60 caracteres -->
<meta name="description" content="…">   <!-- 120–155 caracteres -->
<link rel="canonical" href="https://<dominio>/caminho">
<meta property="og:type" content="website">
<meta property="og:site_name" content="MK Negócios">
<meta property="og:locale" content="pt_BR">
<meta property="og:title" content="…">
<meta property="og:description" content="…">
<meta property="og:url" content="https://<dominio>/caminho">
<meta property="og:image" content="https://<dominio>/…/imagem.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="…">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="…">  <!-- cor da marca (WIL-16) -->
```

Modelos de título e descrição:

| Página | `<title>` | `description` |
| --- | --- | --- |
| Início | MK Negócios — Imóveis e Contabilidade em Ubatuba e Caraguatatuba | Imóveis à venda e para alugar, contabilidade e abertura de empresas em Ubatuba e Caraguatatuba. Fale com a MK Negócios pelo WhatsApp. |
| Listagem cidade | Imóveis em {Cidade} — Casas, Apartamentos e Terrenos \| MK Negócios | {N} imóveis à venda e para alugar em {Cidade}, SP. Veja fotos, preços e fale direto pelo WhatsApp. |
| Imóvel | {Tipo} {à venda\|para alugar} em {Bairro}, {Cidade} — {Ref} | {Tipo} com {quartos} quartos, {área} m², em {Bairro}, {Cidade}. {Preço}. Código {Ref}. Fale com a MK Negócios. |
| Serviço contábil | {Serviço} em Ubatuba e Caraguatatuba \| MK Negócios | Resumo de 1 frase do serviço + "Atendimento em Ubatuba e Caraguatatuba. Peça orçamento pelo WhatsApp." |
| Contato | Contato — MK Negócios \| Ubatuba e Caraguatatuba | WhatsApp (12) 99729-8895, e-mail e formulário. |

- Títulos e descrições **únicos** por página; gerar automaticamente para
  imóveis a partir dos campos do cadastro, com campo opcional para o
  administrador sobrescrever.
- `og:image` do imóvel = **foto de capa** recortada em 1200×630, JPEG,
  **até ~300 KB** (o WhatsApp falha em mostrar prévias de imagens grandes).
  Páginas institucionais usam uma imagem padrão da marca.
- Um único `<h1>` por página, coerente com o título.

## Dados estruturados (JSON-LD)

Não geram "estrelas" automaticamente, mas ajudam o Google a entender
negócio, cidades e páginas. Validar no
[Teste de resultados avançados](https://search.google.com/test/rich-results) e no
[Schema Markup Validator](https://validator.schema.org/).

**Em todas as páginas — organização** (dados `[A CONFIRMAR]` só entram
quando reais; nunca publicar endereço fictício):

```json
{
  "@context": "https://schema.org",
  "@type": ["RealEstateAgent", "AccountingService"],
  "@id": "https://<dominio>/#empresa",
  "name": "MK Negócios",
  "url": "https://<dominio>/",
  "logo": "https://<dominio>/logo.png",
  "telephone": "+55-12-99729-8895",
  "email": "contato@mknegocios.com",
  "areaServed": [
    { "@type": "City", "name": "Ubatuba", "containedInPlace": { "@type": "State", "name": "São Paulo" } },
    { "@type": "City", "name": "Caraguatatuba", "containedInPlace": { "@type": "State", "name": "São Paulo" } }
  ],
  "address": "[A CONFIRMAR — PostalAddress somente se houver endereço físico de atendimento]",
  "openingHoursSpecification": "[A CONFIRMAR]",
  "sameAs": ["[Instagram, Facebook, Perfil da Empresa no Google — A CONFIRMAR]"]
}
```

**Páginas internas — `BreadcrumbList`** (Início › Imóveis › Ubatuba › Ref).

**Página de serviço — `Service`:**

```json
{
  "@context": "https://schema.org",
  "@type": "Service",
  "name": "Abertura e regularização de empresas",
  "serviceType": "Contabilidade",
  "provider": { "@id": "https://<dominio>/#empresa" },
  "areaServed": ["Ubatuba", "Caraguatatuba"]
}
```

**Página do imóvel — `RealEstateListing`** com `offers` (`price`,
`priceCurrency: BRL`, `availability`), `image` (fotos), `datePosted`. Não
incluir coordenadas exatas: o WIL-18 pede **localização aproximada** —
usar só bairro/cidade.

## Sitemap

- `/sitemap.xml` gerado dinamicamente (índice), com:
  - `sitemap-paginas.xml` — páginas institucionais e de serviço;
  - `sitemap-imoveis.xml` — **somente imóveis publicados e disponíveis**,
    com `<lastmod>` = última atualização real do imóvel.
- Não incluir: rascunhos, vendidos/alugados, URLs com filtros, páginas
  `noindex`, redirecionamentos.
- `lastmod` deve mudar só quando o conteúdo muda (não usar a data de geração).
- Enviar ao Google Search Console e ao Bing Webmaster Tools após o
  lançamento.
- Implementação: dá para gerar com uma rota/Blade simples. Adicionar pacote
  (ex.: `spatie/laravel-sitemap`) exige aprovação de dependência (AGENTS.md).

## robots.txt do site

```
User-agent: *
Disallow: /admin
Disallow: /painel
Disallow: /*?*ordenar=

Sitemap: https://<dominio>/sitemap.xml
```

Ajustar os caminhos ao que o painel de imóveis (WIL-17) usar. O painel
também deve responder com `X-Robots-Tag: noindex`.

## Imagens

- Gerar versões redimensionadas no upload (ex.: 400, 800, 1600 px de
  largura) em **WebP** (com JPEG de fallback se necessário) e servir com
  `srcset`/`sizes`.
- `width`/`height` explícitos (evita CLS); `loading="lazy"` em tudo exceto
  a foto principal (que deve ter `fetchpriority="high"`).
- Texto alternativo gerado: "{Tipo} em {Bairro}, {Cidade} — foto {n}".
- Remover metadados EXIF com GPS das fotos enviadas (privacidade do
  proprietário e coerência com a "localização aproximada").
- Nome de arquivo descritivo: `mk0123-casa-itagua-ubatuba-1.webp`.

## Performance (Core Web Vitals, celular)

Metas no PageSpeed Insights (mobile), medidas na página inicial, numa
listagem e numa página de imóvel:

- LCP < 2,5 s · INP < 200 ms · CLS < 0,1
- Performance ≥ 85 e SEO = 100 no Lighthouse.

## Outros

- Página 404 útil (busca de imóveis + WhatsApp), retornando HTTP 404 real.
- `favicon`, `apple-touch-icon` e `site.webmanifest` com a identidade (WIL-16).
- Sem conteúdo duplicado entre o site e portais (ZAP, OLX, VivaReal): a
  descrição no site deve ser a versão mais completa.
