# Totem de Impressao Autonoma 24h

Epic: WIL-48 | Status: Ativo

## Descricao
Totem de impressao autonoma para lan house. Cliente le QR code, abre pagina no celular, envia arquivo, ajusta layout e quantidade de copias, paga via Pix e retira na boquinha do totem 24h.

## Tickets Vinculados

| Ticket | Titulo | Status | Prioridade |
|--------|--------|--------|------------|
| WIL-45 | Totem de Impressao Autonoma 24h | Backlog | Alta |
| WIL-46 | Pesquisa Back — Tecnologias para Totem de Impressao | Concluido | Alta |
| WIL-47 | Pesquisa Front — Tecnologias e UX para Totem de Impressao | Concluido | Alta |

## Frontend (WIL-47)

Telas implementadas:
- Check-in: selecao de estacao, plano, reservar
- Maquina: timer gigante com anel de progresso
- Painel admin: dashboard com metricas

## Backend (WIL-46)

- Geracao de QR code
- Recebimento de arquivos para impressao
- Fila de impressao
- Integracao com impressora termica/jato de tinta
- Gerenciamento de fila de impressao

## Worktrees

| Worktree | Ticket | Estado |
|----------|--------|--------|
| totem-back | WIL-46 | working |
| totem-front | WIL-47 | working |

---

Ultima atualizacao: 21/11/2026
