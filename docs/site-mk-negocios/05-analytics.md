# 05 — Analytics e medição

## Ferramentas

| Ferramenta | Para quê | Custo |
| --- | --- | --- |
| **Google Search Console** | Indexação, buscas que trazem visitas, erros, sitemap | Gratuito — **obrigatório** |
| **Bing Webmaster Tools** | Idem para Bing (importa do Search Console) | Gratuito |
| **Google Analytics 4** | Visitas, origem, conversões (WhatsApp/formulário) | Gratuito |
| Perfil da Empresa no Google | Ligações, rotas, cliques no site a partir do mapa | Gratuito |

Alternativa ao GA4 sem cookies (dispensa banner): Plausible ou Umami.
Recomendação: **GA4 com Consent Mode v2** — gratuito e integra com Google
Ads caso haja anúncios no futuro. Decisão final do proprietário.

## Consentimento (LGPD)

- GA4 carregado com Consent Mode v2 em **`denied` por padrão**
  (`analytics_storage`, `ad_storage`, `ad_user_data`,
  `ad_personalization`).
- Banner simples: "Aceitar" / "Recusar" com o mesmo destaque, link para a
  Privacidade. Guardar a escolha (cookie/localStorage, 12 meses) e permitir
  mudar pelo rodapé ("Preferências de cookies").
- Sem consentimento, nenhum cookie de analytics é gravado.
- ID de medição (`G-XXXXXXX`) em variável de ambiente; **desligado** em
  local/homologação.

## Eventos

Nomes em `snake_case`, parâmetros consistentes em todo o site.

| Evento | Quando | Parâmetros | Conversão? |
| --- | --- | --- | --- |
| `whatsapp_click` | clique em qualquer botão de WhatsApp | `contexto` (imovel/servico/geral), `referencia`, `posicao` (topo/flutuante/barra_fixa/rodape), `cidade` | **Sim** |
| `generate_lead` | formulário enviado com sucesso (resposta do servidor) | `assunto`, `referencia`, `cidade` | **Sim** |
| `phone_click` | clique em `tel:` | `posicao` | Sim |
| `email_click` | clique em `mailto:` | `posicao` | Não |
| `view_item` | visualização da página de imóvel | `referencia`, `tipo`, `finalidade`, `cidade`, `bairro`, `preco` | Não |
| `search` / `filtrar_imoveis` | aplicação de filtros na listagem | `cidade`, `tipo`, `finalidade`, `quartos` | Não |
| `share` | botão compartilhar imóvel | `metodo`, `referencia` | Não |

Observação: o GA4 mede o **clique** no WhatsApp, não se a conversa
aconteceu. Para fechar o ciclo, o atendente pode registrar no painel/
planilha a origem dos negócios ("Site — MK0123").

## UTMs para divulgação

Usar UTMs em links publicados fora do site (nunca em links internos):

| Canal | Exemplo |
| --- | --- |
| Instagram bio | `?utm_source=instagram&utm_medium=social&utm_campaign=bio` |
| Post de imóvel | `?utm_source=instagram&utm_medium=social&utm_campaign=imovel_mk0123` |
| Status/lista do WhatsApp | `?utm_source=whatsapp&utm_medium=social&utm_campaign=imovel_mk0123` |
| Perfil da Empresa no Google | `?utm_source=google&utm_medium=organic&utm_campaign=gbp` |
| Panfleto/placa (QR code) | `?utm_source=placa&utm_medium=offline&utm_campaign=imovel_mk0123` |

A página com UTM deve manter `canonical` sem UTM.

## Relatório mensal mínimo

1. Visitas por origem (Google orgânico, Instagram, WhatsApp, direto).
2. Conversões (`whatsapp_click` + `generate_lead`) por página e por
   imóvel/serviço.
3. Top 10 consultas no Search Console por cidade (Ubatuba × Caraguatatuba).
4. Imóveis mais vistos × imóveis com mais contatos.
5. Ações do Perfil da Empresa (ligações, rotas, cliques).
