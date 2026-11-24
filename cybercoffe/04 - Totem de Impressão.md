# Totem de Impressão Autônoma 24h

Epic: WIL-48 | Status: 🟡 Ativo

## Descrição
Totem de impressão autônoma para lan house. Cliente lê QR code, abre página no celular, envia arquivo, ajusta layout e quantidade de cópias, paga via Pix e retira na boquinha do totem 24h.

## Tickets Vinculados

| Ticket | Título | Status | Prioridade |
|--------|--------|--------|------------|
| WIL-45 | Totem de Impressão Autônoma 24h | Backlog | Alta |
| WIL-46 | Pesquisa Back — Totem de Impressão Autônoma | ✅ Concluído | Alta |
| WIL-47 | Pesquisa Front — Totem de Impressão Autônoma | ✅ Concluído | Alta |

## Frontend (WIL-47)

Telas implementadas:
- Check-in: seleção de estação, plano, reservar
- Máquina: timer gigante com anel de progresso
- Painel admin: dashboard com métricas

## Backend (WIL-46)

- Geração de QR code
- Recebimento de arquivos para impressão
- Fila de impressão
- Integração com impressora térmica/jato de tinta
- Gerenciamento de fila de impressão

## Worktrees

| Worktree | Ticket | Estado |
|----------|--------|--------|
| totem-back | WIL-46 | working |
| totem-front | WIL-47 | working |

---

Última atualização: 21/11/2026
