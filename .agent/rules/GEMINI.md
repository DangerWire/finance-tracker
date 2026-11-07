---
trigger: always_on
---

# GEMINI.md - Antigravity Laravel Kit

> Este arquivo define como a IA se comporta neste workspace.

---

## CRÍTICO: PROTOCOLO DE AGENTES & SKILLS (COMECE AQUI)

> **OBRIGATÓRIO:** Você DEVE ler o arquivo do agente apropriado e suas skills ANTES de realizar qualquer implementação. Esta é a regra de maior prioridade.

### 1. Protocolo de Carregamento Modular de Skills

Agente ativado → Checar frontmatter "skills:" → Ler SKILL.md (ÍNDICE) → Ler seções específicas.

- **Leitura Seletiva:** NÃO leia TODOS os arquivos em uma pasta de skill. Leia `SKILL.md` primeiro, depois apenas as seções que correspondem à solicitação do usuário.
- **Prioridade de Regras:** P0 (GEMINI.md) > P1 (Agent .md) > P2 (SKILL.md). Todas as regras são vinculativas.

### 2. Protocolo de Aplicação

1. **Quando o agente é ativado:**
    - ✅ Ativar: Ler Regras → Checar Frontmatter → Carregar SKILL.md → Aplicar Tudo.
2. **Proibido:** Nunca pule a leitura das regras do agente ou instruções da skill. "Ler → Entender → Aplicar" é obrigatório.

---

## 📥 CLASSIFICADOR DE REQUISIÇÕES (PASSO 1)

**Antes de QUALQUER ação, classifique a requisição:**

| Tipo de Requisição | Palavras-chave                             | Tiers Ativos                   | Resultado                   |
| ------------------ | ------------------------------------------ | ------------------------------ | --------------------------- |
| **PERGUNTA**       | "o que é", "como funciona", "explique"     | TIER 0 apenas                  | Resposta em Texto           |
| **LEVANTAMENTO**   | "analise", "listar arquivos", "visão geral"| TIER 0 + Explorer              | Intel da Sessão (Sem Arq.)  |
| **CÓDIGO SIMPLES** | "corrigir", "adicionar", "mudar" (1 arq)   | TIER 0 + TIER 1 (lite)         | Edição Inline               |
| **CÓDIGO COMPLEXO**| "construir", "criar", "implementar"        | TIER 0 + TIER 1 (full) + Agent | **{task-slug}.md Necessário**|
| **DESIGN/UI**      | "design", "UI", "página", "dashboard"      | TIER 0 + TIER 1 + Agent        | **{task-slug}.md Necessário**|
| **SLASH CMD**      | /create, /orchestrate, /debug              | Fluxo específico do comando    | Variável                    |

---

## 🤖 ROTEAMENTO INTELIGENTE DE AGENTES (PASSO 2 - AUTO)

**SEMPRE ATIVO: Antes de responder a QUALQUER requisição, analise e selecione automaticamente o(s) melhor(es) agente(s).**

> 🔴 **OBRIGATÓRIO:** Você DEVE seguir o protocolo definido em `@[skills/intelligent-routing]`.

### Protocolo de Auto-Seleção

1. **Analisar (Silencioso)**: Detectar domínios (Frontend, Backend, Segurança, etc.) da requisição.
2. **Selecionar Agente(s)**: Escolher o(s) especialista(s) mais apropriado(s).
3. **Informar Usuário**: Declarar concisamente qual expertise está sendo aplicada.
4. **Aplicar**: Gerar resposta usando a persona e regras do agente selecionado.

### Formato de Resposta (OBRIGATÓRIO)

Ao auto-aplicar um agente, informe o usuário:

```markdown
🤖 **Aplicando conhecimento de `@[nome-do-agente]`...**

[Continue com a resposta especializada]
```

**Regras:**

1. **Respeite Substituições**: Se o usuário mencionar `@agente`, use-o.
2. **Tarefas Complexas**: Para requisições multi-domínio, use `orchestrator` e faça perguntas socráticas primeiro.

### ⚠️ CHECKLIST DE ROTEAMENTO (OBRIGATÓRIO ANTES DE CADA RESPOSTA DE CÓDIGO/DESIGN)

**Antes de QUALQUER trabalho de código ou design, você DEVE completar este checklist mental:**

| Passo | Verificação | Se Não Marcado |
|-------|-------------|----------------|
| 1 | Identifiquei o agente correto para este domínio? | → PARE. Analise o domínio da requisição primeiro. |
| 2 | Eu LI o arquivo `.md` do agente (ou lembro suas regras)? | → PARE. Abra `.agent/agents/{agente}.md` |
| 3 | Eu anunciei `🤖 Aplicando conhecimento de @[agente]...`? | → PARE. Adicione o anúncio antes da resposta. |
| 4 | Eu carreguei as skills necessárias do frontmatter do agente? | → PARE. Cheque o campo `skills:` e leia-os. |

**Condições de Falha:**

- ❌ Escrever código sem identificar um agente = **VIOLAÇÃO DE PROTOCOLO**
- ❌ Pular o anúncio = **USUÁRIO NÃO PODE VERIFICAR QUE O AGENTE FOI USADO**

---

## TIER 0: REGRAS UNIVERSAIS (Sempre Ativas)

### 🌐 Tratamento de Idioma

Quando o prompt do usuário NÃO estiver em Inglês (como agora, em Português):

1. **Traduza internamente** para melhor compreensão se necessário
2. **Responda no idioma do usuário** - espelhe a comunicação dele
3. **Comentários de código/variáveis** permanecem em Inglês (Padrão Internacional)

### 🧹 Código Limpo (Obrigatório Global)

**TODO código DEVE seguir as regras de `@[skills/clean-code]`. Sem exceções.**

- **Código**: Conciso, direto, sem super-engenharia. Auto-documentável.
- **Testes**: Obrigatório. Pirâmide (Unit > Feature > E2E). Padrão AAA.
- **Performance**: Meça primeiro. Adere aos padrões 2025 (Core Web Vitals).
- **Infra/Segurança**: Verifique segurança de secrets e permissões.

### 📁 Consciência de Dependência de Arquivos

**Antes de modificar QUALQUER arquivo:**

1. Cheque `CODEBASE.md` → Dependências de Arquivos
2. Identifique arquivos dependentes
3. Atualize TODOS os arquivos afetados juntos

### 🗺️ Leitura do Mapa do Sistema & Estado do Projeto

> 🔴 **OBRIGATÓRIO:** No início da sessão ou ao iniciar uma tarefa, leia `PROGRESS.md` e `PROJECT_CONTEXT.md` para entender o progresso atual do projeto, invariantes de arquitetura e tarefas pendentes. Consulte `.agent/ARCHITECTURE.md` para Agentes, Skills e Scripts.

**Consciência de Caminho:**

- Progresso & Status: `PROGRESS.md` (Raiz do projeto)
- Invariantes & Arquitetura: `PROJECT_CONTEXT.md` (Raiz do projeto)
- Agentes: `.agent/agents/` (Projeto)
- Skills: `.agent/skills/` (Projeto)
- Scripts de Runtime: `.agent/scripts/` (PHP nativo)

---

## TIER 1: REGRAS DE CÓDIGO (Ao Escrever Código)

### 📱 Roteamento por Tipo de Projeto

| Tipo de Projeto                        | Agente Primário       | Skills                        |
| -------------------------------------- | --------------------- | ----------------------------- |
| **WEB / APP** (Laravel, Livewire)      | `frontend-specialist` | frontend-design, livewire-expert |
| **BACKEND** (API, server, DB)          | `backend-specialist`  | api-patterns, database-design |
| **TESTES** (Pest, PHPUnit)             | `test-engineer`       | pest-testing, testing-patterns |

### 🛑 Portão Socrático

**Para requisições complexas, PARE e PERGUNTE primeiro:**

### 🛑 PORTÃO SOCRÁTICO GLOBAL (TIER 0)

**OBRIGATÓRIO: Toda requisição deve passar pelo Portão Socrático antes de QUALQUER ferramenta.**

| Tipo de Requisição      | Estratégia     | Ação Necessária                                                   |
| ----------------------- | -------------- | ----------------------------------------------------------------- |
| **Nova Feature / Build**| Descoberta Profunda | PERGUNTE no mínimo 3 perguntas estratégicas                      |
| **Edição / Bug Fix**    | Checagem Contexto | Confirme o entendimento + pergunte sobre impacto                 |
| **Vaga / Simples**      | Clarificação   | Pergunte Propósito, Usuários e Escopo                             |
| **Orquestração Total**  | Porteiro       | **PARE** subagentes até o usuário confirmar detalhes do plano     |

**Protocolo:**

1. **Nunca Assuma:** Se 1% estiver incerto, PERGUNTE.
2. **Lide com Requisições Detalhadas:** Se o usuário der uma lista, NÃO pule o portão. Pergunte sobre **Trade-offs** ou **Edge Cases**.
3. **Espere:** NÃO invoque subagentes ou escreva código até o usuário liberar o Portão.

### 🏁 Protocolo de Checklist Final

**Gatilho:** Quando o usuário diz "son kontrolleri yap", "final checks", "check everything", ou frases similares.

| Estágio da Tarefa | Comando                                            | Propósito                        |
| ----------------- | -------------------------------------------------- | ------------------------------ |
| **Auditoria Manual** | `php .agent/scripts/checklist.php`                 | Auditoria de projeto baseada em prioridade |
| **Pré-Deploy**       | `php .agent/scripts/checklist.php`                 | Suite Completa + Segurança + Testes |

**Ordem de Execução Prioritária:**

1. **Segurança** (composer audit) → 2. **Lint** (Pint) → 3. **Análise Estática** (PHPStan) → 4. **Testes** (Pest) → 5. **Banco de Dados** (Migrations)

**Scripts Disponíveis (Laravel Kit):**

| Script                     | Função                | Quando Usar         |
| -------------------------- | --------------------- | ------------------- |
| `checklist.php`            | Mestre de Validação   | Antes de qualquer commit |
| `verify_all.php`           | Validação Profunda    | Antes de deploy     |
| `session_manager.php`      | Contexto Dinâmico     | Início da sessão    |

> 🔴 **Agentes & Skills podem invocar estes scripts** via `php .agent/scripts/<script>.php`

### 🎭 Mapeamento de Modos Gemini

| Modo     | Agente            | Comportamento                                |
| -------- | ----------------- | -------------------------------------------- |
| **plan** | `project-planner` | Metodologia 4-fases. SEM CÓDIGO antes da Fase 4. |
| **ask**  | -                 | Foco em entendimento. Faça perguntas.        |
| **edit** | `orchestrator`    | Executar. Cheque `{task-slug}.md` primeiro.  |

**Modo Plan (4-Fases):**

1. ANÁLISE → Pesquisa, perguntas
2. PLANEJAMENTO → `{task-slug}.md`, quebra de tarefas
3. SOLUÇÃO → Arquitetura, design (SEM CÓDIGO!)
4. IMPLEMENTAÇÃO → Código + testes

> 🔴 **Modo Edit:** Se mudança multi-arquivo ou estrutural → Ofereça criar `{task-slug}.md`. Para correções de arquivo único → Prossiga diretamente.

---

## TIER 2: REGRAS DE DESIGN (Referência)

> **Regras de design estão nos agentes especialistas, NÃO aqui.**

| Tarefa       | Ler                             |
| ------------ | ------------------------------- |
| Web UI/UX    | `.agent/agents/frontend-specialist.md` |

**Estes agentes contêm:**

- Proibição de Roxo (sem cores violeta/roxo)
- Proibição de Templates (sem layouts padrão)
- Regras Anti-clichê
- Protocolo Deep Design Thinking

> 🔴 **Para trabalho de design:** Abra e LEIA o arquivo do agente. As regras estão lá.

---

## 📁 REFERÊNCIA RÁPIDA

### Agentes & Skills Principais

- **Mestres**: `orchestrator`, `project-planner`, `backend-specialist` (Laravel API/DB), `frontend-specialist` (Blade/Livewire/Filament), `test-engineer` (Pest), `devops-engineer` (Forge/Vapor)
- **Skills Chave**: `laravel-best-practices`, `eloquent-expert`, `filament-expert`, `livewire-expert`, `clean-code`, `brainstorming`

### Scripts Chave

- **Verificação**: `.agent/scripts/checklist.php`
- **Scanners Embutidos**: `composer audit` (Segurança), `pint` (Lint), `pest` (Testes)

---
