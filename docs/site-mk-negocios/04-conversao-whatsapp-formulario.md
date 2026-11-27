# 04 — Conversão: WhatsApp contextual e formulário (WIL-13)

Objetivo: todo contato chega **já dizendo de qual imóvel ou serviço se
trata**, para responder rápido e medir o que gera negócio.

## 1. WhatsApp

### Número e link

- Exibição: **(12) 99729-8895**
- Formato internacional para o link: `5512997298895`
- Link: `https://wa.me/5512997298895?text={mensagem codificada}`
  - Codificar com `encodeURIComponent` (JS) / `rawurlencode` (PHP).
  - Abrir em nova aba (`target="_blank" rel="noopener"`).
- Centralizar número e modelos em **um único lugar** (config/env do site),
  nunca espalhados pelos componentes.

### Modelos de mensagem

| Contexto | Mensagem pré-preenchida |
| --- | --- |
| Página do imóvel | `Olá! Tenho interesse no imóvel {REF} – {tipo} em {bairro}, {cidade} ({finalidade}). Vi no site: {url}` |
| Imóvel vendido/alugado | `Olá! Vi que o imóvel {REF} já foi {vendido/alugado}. Vocês têm algo parecido em {cidade}?` |
| Listagem de cidade | `Olá! Estou procurando imóvel em {cidade}. Podem me ajudar?` |
| Serviço contábil | `Olá! Gostaria de saber mais sobre {serviço}. Minha cidade: ____. Vim pela página {url}` |
| Contabilidade para MEI | `Olá! Sou MEI e gostaria de ajuda com {serviço}. Vim pela página {url}` |
| Anunciar imóvel | `Olá! Quero anunciar meu imóvel em {cidade} com a MK Negócios.` |
| Genérico (header/rodapé) | `Olá! Vim pelo site da MK Negócios e gostaria de atendimento.` |

Regras:

- `{REF}` é o código de referência do imóvel (WIL-18) — **sempre no início**
  da mensagem para o atendente achar o imóvel no painel.
- `{url}` = URL canônica, **sem UTM** (a mensagem fica visível ao cliente).
- Não incluir preço na mensagem (pode estar desatualizado quando o cliente
  abrir o link).

### Posicionamento dos botões

- **Página do imóvel:** botão principal "Falar sobre este imóvel no
  WhatsApp" acima da dobra (ao lado do preço) e barra fixa no rodapé no
  celular com WhatsApp + "Enviar mensagem" (abre formulário com a referência).
- **Páginas de serviço:** botão após a primeira seção e ao final, com o
  serviço da página.
- **Botão flutuante** global (canto inferior direito) com mensagem do
  contexto da página atual; não cobrir botões de cookies nem a barra fixa do
  imóvel.
- Texto acessível: `aria-label="Conversar no WhatsApp sobre o imóvel MK0123"`.

## 2. Formulário de contato

Aparece em `/contato` e, em versão curta, na página do imóvel e nas páginas
de serviço.

### Campos

| Campo | Tipo | Obrigatório | Observação |
| --- | --- | --- | --- |
| Nome | texto | Sim | máx. 120 |
| WhatsApp/telefone | tel | Sim | máscara `(12) 99999-9999`; validar 10–11 dígitos |
| E-mail | email | Não | |
| Assunto | select | Sim | Comprar imóvel · Alugar imóvel · Anunciar meu imóvel · Contabilidade · Abrir/regularizar empresa · Baixa de empresa · MEI · Outro |
| Referência | hidden (+ exibida como texto) | Não | código do imóvel ou slug do serviço; preenchida automaticamente |
| Cidade | select | Não | Ubatuba · Caraguatatuba · Outra |
| Mensagem | textarea | Sim | máx. 2.000 |
| Consentimento LGPD | checkbox | **Sim** | **desmarcado por padrão** |
| Aceito receber novidades | checkbox | Não | separado, desmarcado; só usar se houver envio de novidades |

Texto do consentimento:

> Concordo com o uso dos meus dados para que a MK Negócios responda a este
> contato, conforme a [Política de Privacidade](/privacidade).

Também gravar (não exibir): página de origem, referência, `utm_*` da
sessão, data/hora, IP e user-agent (para antispam e prova do consentimento),
**versão do texto de consentimento** aceita.

### Após o envio

- Página/mensagem de sucesso: "Recebemos sua mensagem. Responderemos pelo
  WhatsApp ou e-mail em até `[A CONFIRMAR]`." + botão "Prefere agora?
  Fale no WhatsApp" (com a mesma referência).
- Disparar evento de conversão `generate_lead` (ver 05-analytics.md) **só**
  após resposta de sucesso do servidor.
- Notificar `contato@mknegocios.com` com assunto
  `[Site] {Assunto} — {REF ou serviço} — {Nome}` e `Reply-To` = e-mail do
  cliente (quando informado).
- Gravar o lead no banco (não depender só do e-mail, que pode cair no spam).

### Proteção antispam (em camadas, sem atrapalhar o usuário)

1. **Honeypot:** campo oculto (ex.: `website`) via CSS — se preenchido,
   descartar silenciosamente (responder sucesso).
2. **Tempo mínimo:** timestamp assinado no formulário; envios em menos de
   ~3 s são descartados.
3. **Limite de taxa:** ex. 5 envios/hora por IP e 3 por telefone/dia
   (`RateLimiter` do Laravel / middleware `throttle`).
4. **Validação no servidor** de todos os campos; rejeitar links em excesso
   na mensagem.
5. **Cloudflare Turnstile** (opcional, se o spam passar das camadas
   acima): mais leve e privado que reCAPTCHA, gratuito. É dependência
   externa — exige aprovação e menção na Política de Privacidade.
6. Proteção CSRF padrão do Laravel.

## 3. LGPD — requisitos mínimos

- Página `/privacidade` (texto base em 06-conteudo-paginas.md), linkada no
  rodapé e no consentimento.
- Finalidade clara: responder ao contato e conduzir a negociação/serviço.
  Não usar os dados para marketing sem o opt-in separado.
- Canal para o titular exercer direitos (acesso, correção, exclusão):
  `contato@mknegocios.com`. Agentes de tratamento de pequeno porte podem
  dispensar a indicação formal de encarregado (Res. CD/ANPD nº 2/2022), mas
  precisam manter esse canal.
- Retenção: definir prazo para apagar leads que não viraram cliente (ex.:
  12 meses) `[A CONFIRMAR]`; rotina de limpeza automática.
- Acesso aos leads só para usuários autenticados do painel.
- Banner de cookies apenas se houver cookies não essenciais (ver
  05-analytics.md).

## 4. Testes que a implementação deve ter

- Gerador de link do WhatsApp: número correto, `REF` presente, acentos e
  espaços codificados, URL sem UTM.
- Formulário: rejeita sem consentimento; rejeita telefone inválido; aceita
  envio válido e grava `referencia`, `pagina_origem` e versão do
  consentimento; honeypot preenchido não grava lead; excesso de envios
  retorna 429; notificação por e-mail enfileirada (`Mail::fake`/
  `Notification::fake`).
