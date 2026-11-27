# 06 — Conteúdo das páginas (rascunho v1)

Textos-base para o Frontend usar como conteúdo inicial. Tom: direto,
próximo, sem jargão. Trechos `[A CONFIRMAR]` **não podem ir ao ar** com
placeholder — remover a frase se o dado não existir.

---

## Início `/`

**H1:** Imóveis e contabilidade em Ubatuba e Caraguatatuba

**Subtítulo:** Compre, alugue ou anuncie seu imóvel no Litoral Norte — e
conte com a mesma equipe para abrir, organizar ou regularizar sua empresa.

**CTAs:** [Ver imóveis] [Falar no WhatsApp]

**Bloco busca rápida:** Cidade (Ubatuba / Caraguatatuba) · Finalidade
(Comprar / Alugar) · Tipo · [Buscar]

**H2: Imóveis em destaque** — 6 cards (foto, tipo, bairro/cidade, preço,
quartos, área, código). Link "Ver todos os imóveis".

**H2: Onde atuamos**
- **Ubatuba** — casas, apartamentos e terrenos do Centro às praias do norte
  e do sul. [Imóveis em Ubatuba]
- **Caraguatatuba** — opções para morar ou investir, do Centro ao
  Massaguaçu. [Imóveis em Caraguatatuba]

**H2: Contabilidade para quem empreende no litoral**
Cards: Contabilidade em geral · Abertura e regularização · Baixa de empresas
· Assessoria contábil · Contabilidade para MEI → cada um linka para sua
página.

**H2: Quer anunciar seu imóvel?**
Cuidamos da divulgação, das visitas e da negociação. Envie as informações
pelo WhatsApp e retornamos com os próximos passos. [Anunciar meu imóvel]

**H2: Por que a MK Negócios** — 3 itens, somente fatos confirmados:
- Atendimento local em Ubatuba e Caraguatatuba.
- Imóveis e contabilidade no mesmo lugar: ajuda com documentação, empresa
  e negócio.
- Resposta rápida pelo WhatsApp.
- `[A CONFIRMAR: anos de atuação, nº de negócios, CRECI/CRC]`

**Depoimentos:** somente quando houver depoimentos reais autorizados.

---

## Imóveis `/imoveis` e páginas de cidade

**H1 (geral):** Imóveis à venda e para alugar no Litoral Norte
**H1 (cidade):** Imóveis à venda e para alugar em {Cidade}

**Introdução (Ubatuba, ~60 palavras):**
Encontre casas, apartamentos e terrenos em Ubatuba, para morar o ano todo
ou para temporada. Use os filtros para escolher bairro, tipo e faixa de
preço. Cada imóvel tem um código de referência: é só clicar em "Falar no
WhatsApp" que a mensagem já chega com o código, e respondemos com todos os
detalhes.

**Introdução (Caraguatatuba, ~60 palavras):**
Veja imóveis à venda e para alugar em Caraguatatuba, com fotos, preço e
características. Filtre por bairro, tipo e número de quartos. Gostou de
algum? Fale pelo WhatsApp com o código do imóvel e agende uma visita.

**Estado vazio:** Nenhum imóvel encontrado com esses filtros. Conte o que
você procura e avisamos quando surgir algo: [Falar no WhatsApp].

**Fim da página (texto SEO curto, ~120 palavras por cidade):** bairros
atendidos, tipos de imóvel mais procurados, convite para anunciar.
`[Revisar com o corretor para evitar afirmações de mercado não verificadas]`

---

## Página do imóvel `/imoveis/{ref}-{slug}`

Estrutura (dados vêm do cadastro — WIL-17/18):

1. Breadcrumb: Início › Imóveis › {Cidade} › {Ref}
2. **H1:** {Tipo} {à venda|para alugar} em {Bairro}, {Cidade}
3. Galeria (capa primeiro)
4. Preço · Código **{REF}** · selo de status
5. **[Falar sobre este imóvel no WhatsApp]** · [Enviar mensagem]
6. Características (quartos, suítes, banheiros, vagas, área útil/total)
7. Descrição
8. Localização aproximada: "{Bairro}, {Cidade} — SP" (sem endereço exato)
9. Formulário curto com referência preenchida
10. Imóveis semelhantes (mesma cidade/tipo)
11. Rodapé com `[CRECI A CONFIRMAR]`

Aviso padrão sob o preço:
> Valores e disponibilidade sujeitos a confirmação. Consulte condições pelo
> WhatsApp.

---

## Contabilidade `/contabilidade`

**H1:** Contabilidade em Ubatuba e Caraguatatuba

**Introdução:**
Do MEI à pequena empresa, cuidamos da parte contábil e burocrática para
você focar no seu negócio. Atendemos empreendedores de Ubatuba e
Caraguatatuba — inclusive quem trabalha com turismo, comércio e serviços de
temporada.

Cards → páginas de serviço. CTA: [Pedir orçamento no WhatsApp].

**Perguntas frequentes:**
- *Vocês atendem empresas de outras cidades?* `[A CONFIRMAR]`
- *O atendimento é presencial ou online?* `[A CONFIRMAR]`
- *Como funciona o orçamento?* Conte pelo WhatsApp o tipo de empresa e o
  que precisa; retornamos com uma proposta.

### `/contabilidade/contabilidade-geral`
**H1:** Contabilidade para empresas em Ubatuba e Caraguatatuba
Escrituração contábil e fiscal, apuração de impostos, obrigações acessórias
e relatórios para você acompanhar a saúde do negócio. `[Listar só o que o
escritório de fato entrega]`

### `/contabilidade/abertura-e-regularizacao-de-empresas`
**H1:** Abertura e regularização de empresas em Ubatuba e Caraguatatuba
Vamos do enquadramento à empresa funcionando: escolha do tipo jurídico e do
regime tributário, CNPJ, inscrições e alvará na prefeitura. Se sua empresa
está com pendências, ajudamos a identificar e regularizar.
**Como funciona:** 1. Conversa inicial · 2. Lista de documentos ·
3. Registro e inscrições · 4. Empresa pronta para emitir notas.
FAQ: *Quanto tempo leva?* `[A CONFIRMAR — não prometer prazo]` ·
*Quais documentos preciso?* `[A CONFIRMAR]`

### `/contabilidade/baixa-de-empresas`
**H1:** Baixa de empresa (encerramento de CNPJ)
Encerrar uma empresa do jeito certo evita multas e dívidas futuras.
Verificamos pendências, cuidamos das declarações finais e do pedido de
baixa nos órgãos.

### `/contabilidade/assessoria-contabil`
**H1:** Assessoria contábil para pequenas empresas
Acompanhamento próximo para decisões do dia a dia: impostos, pró-labore,
folha, planejamento tributário dentro da lei e organização financeira.
Gancho com o **MK Gestão** (caixa, vendas e despesas organizados facilitam
a contabilidade) — mencionar só quando o produto estiver disponível a
clientes.

### `/contabilidade/contabilidade-para-mei`
**H1:** Contabilidade para MEI em Ubatuba e Caraguatatuba
Abertura do MEI, declaração anual (DASN-SIMEI), emissão de notas, DAS em
atraso e orientação na hora de crescer e migrar para microempresa.

---

## Empresas `/empresas`

**Bloqueio:** o escopo de "Empresas" não está definido (WIL-12 cita
Contabilidade e Empresas como áreas separadas). Possibilidades: compra e
venda de empresas/pontos comerciais, consultoria empresarial, ou o próprio
MK Gestão. Não escrever o texto antes da definição.

---

## Sobre `/sobre`

**H1:** Sobre a MK Negócios
A MK Negócios atua em Ubatuba e Caraguatatuba com imóveis e contabilidade,
`[história, fundadores, ano de início, registros profissionais — A
CONFIRMAR]`. Foto real da equipe/escritório. CTA para Contato.

---

## Contato `/contato`

**H1:** Fale com a MK Negócios
- WhatsApp: **(12) 99729-8895** [Abrir WhatsApp]
- E-mail: **contato@mknegocios.com**
- Atendimento: Ubatuba e Caraguatatuba — SP
- Horário: `[A CONFIRMAR]`
- Endereço/mapa: só se houver atendimento presencial `[A CONFIRMAR]`
- Formulário completo (ver 04-conversao-whatsapp-formulario.md)

---

## Privacidade `/privacidade` (base — revisar com profissional)

**H1:** Política de Privacidade

1. **Quem somos:** MK Negócios, `[razão social e CNPJ A CONFIRMAR]`,
   contato@mknegocios.com.
2. **Dados coletados:** nome, telefone, e-mail, mensagem e assunto enviados
   pelo formulário; dados técnicos (IP, navegador) para segurança; dados de
   navegação via Google Analytics apenas com seu consentimento.
3. **Para que usamos:** responder seu contato e conduzir o atendimento ou
   negociação solicitada; proteger o site contra abusos; com seu
   consentimento separado, enviar novidades.
4. **Compartilhamento:** prestadores necessários ao funcionamento
   (hospedagem, e-mail, Google Analytics `[e Cloudflare Turnstile, se
   usado]`). Não vendemos dados.
5. **Por quanto tempo:** `[prazo A CONFIRMAR]` para contatos que não viraram
   negócio; mais tempo quando exigido por lei (ex.: obrigações contábeis).
6. **Cookies:** essenciais ao funcionamento e, se aceitos, de medição de
   audiência. Você pode mudar sua escolha em "Preferências de cookies".
7. **Seus direitos (LGPD, art. 18):** confirmar, acessar, corrigir, apagar
   e revogar consentimento — pedido para contato@mknegocios.com.
8. **Atualizações:** data da última atualização no topo.

---

## Rodapé (todas as páginas)

MK Negócios · Imóveis e Contabilidade · Ubatuba e Caraguatatuba — SP
(12) 99729-8895 · contato@mknegocios.com
`[CRECI nº — A CONFIRMAR]` · `[CRC-SP nº — A CONFIRMAR]`
Links: Imóveis · Contabilidade · Sobre · Contato · Privacidade ·
Preferências de cookies · `[Instagram/Facebook — A CONFIRMAR]`
