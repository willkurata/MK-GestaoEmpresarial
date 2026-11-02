# MK Gestão --- Documento de Contexto e Continuidade

**Finalidade:** permitir que outro assistente de programação (por
exemplo, Codex) compreenda o produto, as decisões já tomadas e o estado
atual do desenvolvimento sem precisar acessar o histórico de outros
chats.

**Última atualização:** 27/10/2026\
**Projeto local informado:** `C:\Users\WiLLzEEra\Projects\mk-gestao`\
**Idioma de trabalho:** português brasileiro (pt-BR)

> **Instrução importante para o agente que receber este documento:**
> este arquivo é o contexto de referência do projeto, mas não substitui
> a inspeção do código atual. Antes de editar arquivos, examine o
> repositório e confirme o estado real. Não presuma que alterações
> apenas sugeridas em conversas já foram aplicadas. Preserve as decisões
> confirmadas e registre qualquer nova decisão importante neste
> documento ou em documentação versionada do projeto.

------------------------------------------------------------------------

## 1. Visão do produto

**MK Gestão** é um sistema de gestão para pequenos negócios. A ideia
inicial nasceu de um sistema simples de livro-caixa e evoluiu para uma
aplicação de gestão com frente de caixa (PDV), vendas, despesas,
produtos e painéis de acompanhamento.

O primeiro cenário de uso será uma pequena cafeteria, com produtos como
cafés, bolos, sucos e salgados. Os produtos e preços reais ainda não
foram fornecidos; não inventar dados comerciais reais.

Princípio do produto:

> **"Recebe informação simples → transforma em informação útil."**

A V1 deve ser enxuta, fácil de usar e confiável. Não tentar construir um
ERP completo de uma só vez. Implementar em etapas pequenas, verificáveis
e sem adicionar complexidade antes de ela ser necessária.

## 2. Como colaborar no desenvolvimento

-   O usuário está aprendendo desenvolvimento enquanto constrói o
    sistema. Explicar brevemente termos técnicos quando forem
    relevantes.
-   Trabalhar de forma incremental: uma etapa concreta por vez, com
    comandos claros e resultados verificáveis.
-   O usuário executa comandos no próprio Windows PowerShell e
    compartilha os resultados. Não afirmar que comandos foram executados
    no computador do usuário.
-   Antes de modificar arquivos, ler a versão atual e preservar
    componentes existentes quando fizer sentido.
-   Evitar reescrever grandes áreas ou instalar dependências sem
    necessidade.
-   Ao propor mudanças, informar os arquivos envolvidos e como testar.
-   Não inserir credenciais, senhas ou segredos em código versionado.
    Nunca solicitar que o usuário cole a senha do MySQL no chat.
-   Manter isolamento rigoroso entre empresas (multi-tenant).
-   Não usar dados financeiros fictícios como se fossem reais. Em telas
    sem dados, usar estados vazios, traços ou indicação explícita de
    demonstração.
-   Não apagar histórico financeiro ou operacional para "limpar" dados.
    Preferir cancelamento, inativação ou estorno conforme as regras
    abaixo.

## 3. Tecnologias escolhidas

Decisões confirmadas:

-   **Backend:** PHP com Laravel.
-   **Frontend:** Vue.js.
-   **Integração entre telas e backend:** Inertia.
-   **Estilização:** Tailwind CSS.
-   **Banco de dados:** MySQL.
-   **Testes:** Pest foi adicionado durante a configuração inicial.
-   **Ferramenta de desenvolvimento de frontend:** Vite, incluído na
    estrutura.
-   **Ambiente local de PHP:** Laravel Herd.

Usar as versões instaladas no projeto e inspecionar `composer.json` e
`package.json` antes de alterar dependências. Não assumir versões apenas
pelo documento.

O projeto foi criado pelo instalador Laravel com o starter kit Vue e
autenticação nativa do Laravel. Durante a instalação também foram
executadas etapas do Laravel Boost. O log mostrou configuração de
guidelines, skills e MCP para **Claude Code**, além de migrações para
passkeys e colunas de autenticação em dois fatores. Isso não prova que
todas essas funcionalidades estejam habilitadas no fluxo de login;
verificar o código e as configurações antes de concluir.

## 4. Escopo confirmado da V1

### Incluído ou planejado para a V1

-   Login de usuários.
-   Cadastro administrativo de empresas e usuários pela equipe MK.
-   Separação dos dados por empresa.
-   Perfis de acesso por função.
-   Dashboard simplificado e avançado.
-   Produtos com nome e preço.
-   Frente de caixa / PDV.
-   Carrinho com vários produtos, quantidades e desconto.
-   Pagamento por Pix manualmente confirmado ou dinheiro.
-   Abertura, movimentação e fechamento de caixa.
-   Registro de despesas fixas e variáveis.
-   Estorno integral de venda com regras de autorização e registro.
-   Relatórios gerados sob demanda a partir dos dados existentes.
-   Preservação de histórico de operações.

### Explicitamente fora da V1 (backlog de V2)

-   Planos, assinaturas, cobrança e automação de pagamento.
-   NFC-e e integração fiscal.
-   Estoque completo, código de barras, insumos, receitas, fornecedores,
    compras e movimentações de estoque.
-   Pagamentos divididos.
-   Devoluções parciais de itens.
-   Integrações bancárias e de cartão, QR Code Pix dinâmico, webhooks e
    automação de confirmação.
-   Contas a pagar, vencimentos, recorrência automática, juros, multas e
    conciliação.
-   Permissões personalizadas granulares.
-   Funcionalidades avançadas de fluxo de caixa e centros de custo.
-   Integrações adicionais que aumentem a complexidade antes de validar
    a V1.

A V1 não precisa guardar PDFs prontos de todos os relatórios. Deve
manter os dados e gerar os relatórios quando solicitados.

## 5. Empresas, usuários e isolamento de dados

### Empresas

A equipe MK cadastra cada empresa inicialmente. Não haverá fluxo público
de contratação/cadastro de empresas na V1.

Campos planejados: - `id` --- chave primária. - `cnpj` --- obrigatório e
único globalmente. - `nome` --- obrigatório. - `status` ---
ativa/inativa. - `data_cadastro`.

Cada registro operacional deve estar associado à empresa correspondente.
Todas as consultas e alterações devem verificar o contexto da empresa
para impedir vazamento de dados entre clientes.

### Usuários do cliente

Perfis V1: - **Administrador** - **Moderador** - **Funcionário**

O Moderador é um supervisor de confiança e pode fazer estornos. O
Funcionário não pode estornar por padrão.

Campos planejados: - `id` - `empresa_id` --- chave estrangeira. - `cpf`
--- obrigatório; unicidade dentro da empresa, não global. - `nome` ---
obrigatório. - `usuario` --- nome de login, obrigatório; unicidade
dentro da empresa. - `senha` --- obrigatória, armazenada somente como
hash seguro. - `perfil` - `status` --- ativo/inativo.

A mesma pessoa pode trabalhar em empresas diferentes. O mesmo nome de
usuário ou CPF pode, portanto, aparecer em empresas distintas; a
unicidade é por empresa. Usuários inativos não podem entrar no sistema,
mas seus registros históricos devem permanecer.

A equipe MK cria empresas e usuários inicialmente. Senhas nunca devem
ser armazenadas em texto puro em produção. O usuário pode alterar sua
senha; não foi definida exigência de troca obrigatória no primeiro
login.

O fluxo de login do cliente deverá considerar a empresa (ou contexto
equivalente), o usuário e a senha. Uma sessão segura pode ser mantida;
nunca armazenar a senha em texto puro no navegador.

### Painel administrativo MK

Deve ser separado do ambiente operacional dos clientes.

-   **Super Admin:** administração ampla da plataforma e suporte
    inicial, incluindo acesso e manutenção de dados de empresas conforme
    as regras de segurança.
-   **Financeiro:** função conceitualmente prevista, mas não necessária
    até existir cobrança/assinaturas; pode ficar fora da implementação
    inicial.
-   **Suporte:** não é um perfil separado na V1.

No futuro, considerar log de auditoria para acessos e ações sensíveis.
Mesmo administradores devem preferir inativar/cancelar em vez de apagar
registros financeiros.

## 6. Produtos

Campos planejados: - `id` - `empresa_id` - `nome` --- obrigatório. -
`preco_venda` --- obrigatório. - `custo` --- opcional. - `status` ---
ativo/inativo.

Produtos pertencem a uma empresa. Nomes iguais em empresas diferentes
são permitidos.

Produtos inativos não aparecem para novas vendas, mas permanecem nos
itens de vendas antigas.

Se houver custo informado, o sistema pode calcular lucro bruto e margem.
Se o custo não estiver preenchido, não presumir custo, lucro ou margem.
Quando disponível, o custo aplicado no momento da venda deve ser
guardado como snapshot histórico (cópia do valor naquele momento).

Não editar o preço de uma linha já adicionada ao carrinho se o preço do
cadastro mudar. O preço é copiado para o item ao adicioná-lo ao
carrinho. Remover e adicionar de novo usa o preço atual.

V1 não inclui código de barras, estoque, ingredientes, receitas,
fornecedores, unidades fracionadas ou compras.

## 7. Vendas e carrinho

A tela de PDV deve funcionar como um caixa de supermercado.

-   Seleção visual dos produtos e pesquisa por nome.
-   Sem código de barras na V1.
-   Vários produtos por venda.
-   Ao clicar repetidamente no mesmo produto, incrementar a quantidade
    na mesma linha.
-   Controles `+` e `−`; quantidade zero remove o item.
-   Quantidades são números inteiros na V1.
-   Desconto sobre a venda inteira: valor fixo ou percentual.
-   O desconto não pode tornar o total negativo.
-   Armazenar o desconto final em moeda (por exemplo, 10% de R\$ 50 é
    guardado como R\$ 5).

Campos planejados para venda: - `id` - `empresa_id` - `usuario_id` ---
operador. - `caixa_id` - `data_hora` - `status` - `subtotal` -
`desconto` - `total`

Estados: - `Em andamento` - `Aguardando pagamento` - `Finalizada` -
`Estornada`

Carrinhos cancelados antes do pagamento não têm efeito financeiro e não
precisam de registro histórico importante na V1.

Campos planejados para item da venda: - `id` - `venda_id` -
`produto_id` - `quantidade` - `preco_unitario` --- snapshot. -
`custo_unitario` --- snapshot opcional.

Valores históricos de venda e itens não mudam quando o cadastro do
produto muda. V1 permite estorno integral da venda; devolução parcial
fica para V2.

## 8. Pagamentos e estornos

Pagamento é uma entidade separada da venda.

V1 aceita somente: - **Pix:** confirmação manual pelo operador. -
**Dinheiro:** registrar o valor recebido e o troco quando aplicável.

Uma venda tem um único pagamento na V1. Pagamentos divididos ficam para
V2.

Campos planejados para pagamento: - `id` - `venda_id` - `metodo` -
`valor` - `status` - `data_hora` - `valor_recebido` (para dinheiro,
conforme aplicável) - `troco` (para dinheiro, conforme aplicável)

Exemplo: venda de R\$ 25 com recebimento de R\$ 30 em dinheiro gera
troco de R\$ 5; a entrada líquida no caixa é R\$ 25.

### Estorno integral

-   Permitido por padrão apenas a Administrador ou Moderador.
-   Funcionário não pode estornar.
-   Motivo obrigatório.
-   Registrar usuário responsável e data/hora.
-   Não apagar nem alterar a venda/pagamento original.
-   Criar registro de estorno ligado ao pagamento original, com valor,
    método, motivo, responsável e data/hora.
-   Reverter o efeito financeiro no mesmo meio: um Pix de R\$ 30 gera
    movimento de estorno de −R\$ 30 em Pix; dinheiro gera saída de
    caixa.
-   Impedir estorno duplicado da mesma venda.
-   Sem estoque na V1, portanto estorno não altera estoque.

NFC-e e regras fiscais de cancelamento/devolução são V2.

## 9. Caixa e turnos

Há um cadastro permanente de caixa, separado das aberturas/turnos. Não
criar um novo caixa por dia ou por operador.

Campos planejados para caixa: - `id` - `empresa_id` - `nome` -
`usuario_id` --- usuário associado. - `status` --- ativo/inativo.

Cada abertura/turno: - `id` - `caixa_id` - `usuario_id` ---
responsável. - `data_hora_abertura` - `valor_inicial` - `status` ---
aberto/fechado.

Ciclo: abertura → movimentos → fechamento → conferência.

Quando o operador mudar, fechar o turno anterior e abrir outro no mesmo
cadastro permanente de caixa. O valor inicial seguinte deve ser o
dinheiro realmente contado/entregue, não uma cópia automática do saldo
esperado do turno anterior. Diferenças na troca devem ficar visíveis.

Retiradas de dinheiro devem estar vinculadas à abertura/turno
específico: - `id` - `abertura_id` - `usuario_id` - `valor` -
`descricao` --- obrigatória. - `data_hora`.

Cálculo esperado de dinheiro físico: **valor inicial + vendas em
dinheiro − retiradas**. Pix não aumenta o dinheiro físico.

Fechamento registra turno, operador, horário, valor esperado, valor
contado e diferença. Permitir fechar mesmo com diferença, mas
registrá-la para conferência; nunca corrigir silenciosamente.

Manter histórico de movimentações e fechamentos. Operações ficam
desabilitadas quando o caixa está fechado. A tela deve mostrar "Caixa
fechado" e permitir "Abrir caixa" para usuários autorizados; com caixa
aberto, priorizar o PDV e ações como retirada e fechamento.

## 10. Despesas

Menu dedicado de despesas.

Campos planejados: - `id` - `empresa_id` - `nome` --- obrigatório. -
`valor` --- obrigatório. - `tipo` --- fixa ou variável. - `data` ---
obrigatória; padrão hoje, mas editável.

"Fixa" significa despesa estrutural/recorrente (por exemplo, contas de
consumo), não que o valor seja sempre idêntico. "Variável" significa
eventual/não estrutural.

Registrar uma despesa não deve subtrair automaticamente o dinheiro do
caixa, pois na V1 não há método/data de pagamento. Não incluir ainda
vencimento, pagamento, contas a pagar, recorrência automática, juros,
multas, fornecedores, bancos, conciliação, parcelamento ou centro de
custo.

Edição simples de despesas é permitida na V1, sem implementar de início
um histórico completo de cada edição. Preservar dados financeiros de
forma sensata.

## 11. Dashboard e relatórios

Filtros de período compartilhados: - Hoje. - Últimos 7 dias. - Mês
atual. - Mês anterior. - Período personalizado.

### Dashboard simplificado

Dois gráficos de pizza: 1. Vendas por meio de pagamento: Pix x Dinheiro.
2. Despesas por tipo: Fixa x Variável.

### Dashboard avançado

Pode apresentar: - Total e quantidade de vendas. - Distribuição por meio
de pagamento. - Produtos vendidos. - Aberturas/fechamentos de caixa,
retiradas e diferenças. - Despesas totais e por tipo. - Custos dos
produtos quando informados.

Dashboard deve refletir somente os dados existentes. Não inventar custos
ou lucros. Relatórios PDF devem ser gerados sob demanda a partir dos
registros.

## 12. Direção visual e UX

Preferências confirmadas: - Identidade visual em azul. - Visual "tech
clean", moderno, organizado e profissional. - Marca em letras
maiúsculas. - Evitar excesso de informação e aparência genérica. -
Layout responsivo para telas menores. - Menu lateral com navegação
clara. - O PDV deverá ser mais direto e rápido que o painel
administrativo.

Estrutura visual inicial proposta: - Marca MK Gestão. - Menu lateral. -
Cabeçalho com empresa e usuário. - Dashboard com cartões e áreas para
gráficos. - Módulos ainda não implementados identificados como "Em
breve", sem links quebrados. - Estados vazios claros em vez de valores
falsos.

## 13. Estado atual do ambiente de desenvolvimento

Informações confirmadas em terminal pelo usuário:

-   Windows PowerShell.
-   Projeto localizado em `C:\Users\WiLLzEEra\Projects\mk-gestao`.
-   PHP CLI: `8.4.16`, instalado via Laravel Herd.
-   Composer: `2.9.3`.
-   Node.js: `v22.16.0`.
-   npm: `11.12.1`.
-   Instalador Laravel global exibido como versão `5.24.0` pelo comando
    `laravel --version`, enquanto o pacote global do Composer foi
    atualizado para `5.32.0`. O comando `laravel` apontava para
    `C:\Users\WiLLzEEra\.config\herd\bin\laravel.bat`; executar
    diretamente o instalador via Composer mostrava a versão atualizada.
    Isso foi um detalhe do ambiente, não um bloqueio.
-   Servidor MySQL instalado e em execução como serviço `MySQL267`.
-   Executável do servidor:
    `C:\Program Files\MySQL\MySQL Server 26.7\bin\mysqld.exe`.
-   Executável cliente:
    `C:\Program Files\MySQL\MySQL Server 26.7\bin\mysql.exe`.
-   Versão retornada pelo cliente e servidor: `26.7.0`, MySQL Community
    Server GPL.
-   Banco `mk_gestao` criado com charset `utf8mb4` e collation
    `utf8mb4_unicode_ci`.
-   Conexão do Laravel com o MySQL foi testada com sucesso quando
    `php artisan migrate` executou as migrations.
-   O usuário configurou `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`,
    `DB_PORT=3306`, `DB_DATABASE=mk_gestao`, `DB_USERNAME=root` e
    informou a senha localmente em `.env`. **Nunca exibir ou pedir o
    valor da senha.**
-   A senha não deve ser commitada. Revisar `.gitignore` para garantir
    que `.env` não seja versionado.
-   O usuário iniciou `composer run dev` e confirmou que a aplicação
    abriu no navegador. O processo deve continuar rodando na janela de
    PowerShell correspondente.

### Migrations executadas

O comando `php artisan migrate` terminou com sucesso e criou: -
`users` - `cache` - `jobs` - `passkeys` - colunas de autenticação em
dois fatores na tabela de usuários - tabela `migrations`

A saída reportou: - `0001_01_01_000000_create_users_table` --- DONE -
`0001_01_01_000001_create_cache_table` --- DONE -
`0001_01_01_000002_create_jobs_table` --- DONE -
`2024_01_01_000000_create_passkeys_table` --- DONE -
`2025_08_14_170933_add_two_factor_columns_to_users_table` --- DONE

Isso é apenas a estrutura inicial do starter kit; as tabelas de negócio
do MK Gestão ainda não foram modeladas/implementadas.

## 14. Arquivos já inspecionados

O usuário executou `Get-Content` e compartilhou o conteúdo destes
arquivos:

### `resources/js/layouts/app/AppSidebarLayout.vue`

-   Importa `AppContent`, `AppShell`, `AppSidebar`, `AppSidebarHeader`,
    `Toaster` e `BreadcrumbItem`.
-   Usa `<AppShell variant="sidebar">`, renderiza `<AppSidebar />`,
    `<AppContent variant="sidebar">`,
    `<AppSidebarHeader :breadcrumbs="breadcrumbs" />` e o slot.
-   Preservar essa estrutura a menos que exista motivo técnico concreto
    para alterá-la.

### `resources/js/components/AppSidebar.vue`

-   Usa o componente `Sidebar` e componentes associados.
-   Importa `Link` do Inertia, ícones de `@lucide/vue`, `AppLogo`,
    `NavFooter`, `NavMain`, `NavUser` e a rota `dashboard`.
-   `mainNavItems` atualmente contém apenas `Dashboard`, usando
    `dashboard()`.
-   `footerNavItems` contém links do starter kit para Repository e
    Documentation.
-   Template renderiza a marca no cabeçalho, `NavMain` no conteúdo, e
    `NavFooter` + `NavUser` no rodapé.
-   O menu do starter kit ainda precisa ser adaptado ao MK Gestão. Não
    deixar links irrelevantes para o usuário final.

### `resources/js/components/NavMain.vue`

-   Recebe `items: NavItem[]`.
-   Usa `Link`, `useCurrentUrl` e componentes de sidebar.
-   Mostra o título de grupo atualmente como `Platform`.
-   Cada item é renderizado como link com ícone e estado ativo.

### `resources/js/components/AppLogo.vue`

O conteúdo original inspecionado importava `usePage` e `AppLogoIcon`,
pegava `usePage().props.name` e exibia o ícone padrão e o nome da
aplicação.

### `resources/js/pages/Dashboard.vue`

O conteúdo original inspecionado era uma tela de placeholders: -
Importava `Head`, `PlaceholderPattern` e `dashboard`. - Definia
breadcrumb `Dashboard`. - Renderizava três cartões com
`PlaceholderPattern` e um bloco grande de placeholder.

### Atenção sobre alterações propostas anteriormente

Depois de inspecionar esses arquivos, foi sugerido ao usuário substituir
`AppLogo.vue` por uma marca textual "MK GESTÃO" com subtítulo "Gestão
inteligente"; alterar o rótulo `Platform` em `NavMain.vue` para
`MENU PRINCIPAL`; e substituir `Dashboard.vue` por uma tela inicial
com: - cabeçalho "Visão geral"; - área de boas-vindas; - quatro cartões
com valores vazios (`—`); - áreas vazias para resumo de vendas e
atividade recente; - ícones importados de `@lucide/vue`.

**O usuário ainda não confirmou se aplicou essas alterações.** O agente
que receber este documento deve inspecionar o estado atual do
repositório antes de presumir que elas estão presentes. Confirmar também
que os nomes de ícones e o pacote de ícones existem na versão instalada
e que o build passa.

## 15. Próximas etapas sugeridas

Seguir esta ordem, confirmando cada etapa:

1.  **Inspecionar o estado atual do repositório.**
    -   `git status`
    -   ler `composer.json`, `package.json`,
        `resources/js/components/AppLogo.vue`,
        `resources/js/components/AppSidebar.vue`,
        `resources/js/components/NavMain.vue`,
        `resources/js/pages/Dashboard.vue` e configurações relevantes.
    -   confirmar se as alterações visuais sugeridas já foram aplicadas.
2.  **Estabilizar o layout principal.**
    -   aplicar identidade MK em azul, menu principal e dashboard.
    -   remover links de exemplo do starter kit do ambiente do cliente.
    -   incluir módulos futuros como "Em breve" sem links quebrados.
    -   verificar responsividade e estados claros.
3.  **Validar o frontend.**
    -   usar scripts existentes em `package.json` para build/lint, sem
        inventar scripts.
    -   corrigir erros de compilação e checar no navegador.
4.  **Verificar autenticação e configuração.**
    -   entender o starter kit e os recursos efetivamente habilitados.
    -   confirmar login básico e comportamento de registro público; a
        aplicação de cliente não deve oferecer cadastro público de
        empresas.
5.  **Definir e implementar a base multiempresa.**
    -   modelos, migrations, relações e escopo por `empresa_id`.
    -   testar isolamento entre empresas antes de avançar.
6.  **Implementar módulos em etapas pequenas:** empresas/usuários,
    produtos, caixa/turnos, vendas/itens/pagamentos/estornos, despesas e
    dashboard conectado aos dados.
7.  **Adicionar testes** para regras críticas: isolamento entre
    empresas, totais/descontos, snapshots de preços/custos, caixa,
    permissões e estorno duplicado.

Não implementar todos os módulos de uma vez. Cada etapa deve ter
migrations, validações, autorização e testes proporcionais à regra.

## 16. Questões técnicas ainda abertas

Estas questões exigem inspeção ou decisão posterior; não inventar
respostas:

-   Versões exatas instaladas de Laravel, Vue, Inertia e Tailwind devem
    ser confirmadas nos arquivos de dependências.
-   **Resolvido em 31/10/2026:** acesso em duas etapas para clientes:
    CNPJ + senha da empresa; depois, no espaço isolado, usuário + senha
    individual. O ambiente MK usa Super Admin separado com acesso a todas
    as empresas.
-   Verificar o sistema de autenticação gerado pelo starter kit,
    incluindo rotas de registro e se devem ser desativadas para
    clientes. Definir também o fluxo operacional para criação e recuperação
    da senha da empresa e das contas individuais.
-   Definir estratégia de backup e recuperação do MySQL antes de
    produção.
-   Definir implantação/hosting e configuração segura de produção mais
    adiante.
-   Definir requisitos legais/fiscais antes de começar qualquer
    implementação fiscal em V2.

## 17. Checklist rápido para o agente

-   [ ] Ler o código atual antes de editar.
-   [ ] Não assumir que mudanças sugeridas no chat foram aplicadas.
-   [ ] Preservar Laravel + Vue + Inertia + Tailwind + MySQL.
-   [ ] Não trocar o banco por SQLite.
-   [ ] Não revelar credenciais nem commitar `.env`.
-   [ ] Garantir isolamento de todas as consultas por empresa.
-   [ ] Não gerar números de vendas, despesas ou lucro fictícios no
    dashboard.
-   [ ] Não apagar vendas, pagamentos, estornos ou fechamentos
    históricos.
-   [ ] Respeitar o escopo da V1 e deixar itens de V2 no backlog.
-   [ ] Fazer uma mudança por vez e executar as verificações
    disponíveis.

------------------------------------------------------------------------

## 18. Mensagem de contexto para colar no Codex

Você pode começar uma sessão do Codex com esta instrução:

> Leia `MK_GESTAO_CONTEXT.md` por completo antes de sugerir ou alterar
> código. Ele contém as decisões de produto, regras da V1, escolhas
> tecnológicas e estado conhecido do projeto MK Gestão. Primeiro
> inspecione o repositório e compare o código real com o documento,
> principalmente o layout Vue e o estado das alterações visuais
> sugeridas. Não presuma que mudanças descritas como propostas já foram
> aplicadas. Depois informe resumidamente o estado atual, os arquivos
> relevantes e o próximo passo mais seguro. Não implemente
> funcionalidades fora do escopo nem altere regras de negócio sem
> confirmar comigo. Explique os termos técnicos brevemente e trabalhe em
> etapas pequenas e verificáveis.

## 19. Fluxo de acesso em duas etapas

**Atualizado em 31/10/2026:** existem dois níveis de acesso para clientes, além do ambiente administrativo da MK:

1. **Administração MK:** acesso Super Admin separado do ambiente dos clientes. A equipe MK pode administrar e visualizar todas as empresas.
2. **Entrada da empresa:** no MKGestão.com, a empresa entra com CNPJ e senha. Esse passo identifica e abre o espaço de trabalho isolado da empresa.
3. **Acesso da pessoa:** dentro do espaço da empresa, cada administrador, moderador ou funcionário entra com seu próprio usuário e senha. As permissões e o operador registrado nas ações seguem o usuário individual.

Depois da entrada da empresa, a interface pode exibir os módulos disponíveis em estado bloqueado/cinza até uma pessoa entrar. Módulos fora da V1, como estoque, continuam indisponíveis ou marcados como “Em breve”, conforme o escopo já definido; a segunda entrada não os habilita automaticamente.

O acesso compartilhado da empresa e as contas individuais são camadas distintas. Permanecem válidas as decisões anteriores sobre papéis, permissões de estorno e registro do operador por usuário. O CNPJ continua identificando a empresa e isolando seus dados.

## 20. Estado de implementação — autenticação

**Atualizado em 31/10/2026:** o esqueleto do fluxo foi ligado ao Laravel:

- guards distintos para Super Admin MK, empresa e usuário da empresa;
- entrada da empresa por CNPJ e senha, com CNPJ normalizado para dígitos;
- entrada individual por usuário e senha dentro do contexto da empresa;
- limite de tentativas nos dois formulários e verificação de status ativo;
- `/dashboard` protegido pela empresa e por um usuário vinculado a ela;
- `/mk/dashboard` reservado ao Super Admin, com lista das empresas;
- cadastro público desativado; o comando `php artisan mk:create-super-admin`
  cria o primeiro acesso master sem senha fixa no código.
- tela master para cadastrar empresa junto com o primeiro Administrador,
  e para adicionar usuários, alterar perfil e ativar/inativar empresa e usuários.
- regra central de autorização `reverse-sales`: Administrador e Moderador
  podem estornar; Funcionário não. A rota de estorno integral usa o middleware
  `company.can:reverse-sales`.

As migrations de `companies` e `company_users` foram aplicadas ao MySQL local
em 31/10/2026. Para validação manual do fluxo existe uma empresa de demonstração
no banco local, com CNPJ `11.111.111/1111-11` e usuário individual `admin`;
esse registro foi criado diretamente no banco, não em migrations ou seeders.
Uma conta master local também foi criada para acesso administrativo
(`admin@mkgestao.test`). Ainda falta decidir o processo operacional de
recuperação de senhas e criar testes de isolamento entre empresas antes de
produção.

## 21. Produtos, caixa e vendas

**Atualizado em 31/10/2026:** primeira fatia operacional da V1 implementada.
As migrations de produtos, caixa, turnos, movimentos, vendas, itens,
pagamentos e estornos foram aplicadas ao MySQL local em 01/11/2026.

- Produtos podem ser cadastrados e editados pela empresa, com preço,
  custo opcional e status ativo/inativo.
- Há um cadastro permanente de caixa por empresa. Só pode haver um turno
  aberto por vez; quando outro usuário mantém o turno aberto, a tela informa
  quem deve fechá-lo antes da troca de operador.
- A venda exige turno aberto e produto ativo; o servidor recalcula os valores,
  guarda snapshots de nome/preço/custo e aceita um único pagamento em Pix ou
  dinheiro.
- Fechamento registra valor esperado, valor contado e diferença. Os movimentos
  Pix e dinheiro ficam identificados separadamente.
- Estorno integral exige Administrador ou Moderador, motivo obrigatório e
  registro do usuário responsável. Para manter o fechamento de turnos já
  encerrados, o movimento do estorno é lançado no turno aberto do usuário que
  realizou a operação; por isso é necessário abrir o caixa antes de estornar.

As páginas estão disponíveis em `/produtos` e `/caixa`. Não foram criados
produtos ou vendas de demonstração; as telas começam vazias.

