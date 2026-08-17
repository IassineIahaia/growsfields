# Growsfields — Checklist v2 (sem dependência do ACF Pro)

> Substitui o `plugin-blocos-checklist.md` original a partir da Fase 2. As Fases 0-1 já feitas mantêm-se válidas, com um item revertido (ver Fase 1).
>
> **Decisão em aberto (confirmar antes da Fase 6-B):** field groups só por JSON manual, ou também uma UI de admin para os criar visualmente? Ver nota na Fase 6-B.

---

## Fase 0 — Preparação ✅ (feita)
- [x] Nome do plugin, prefixo, text domain confirmados
- [x] Git init, `.gitignore`, estrutura de pastas

## Fase 1 — Fundação do plugin
- [x] Header do plugin (`growsfields.php`)
- [x] Constantes (`GS_PATH`, `GS_URL`, `GS_VERSION`)
- [x] Composer autoload (PSR-4)
- [ ] **Reverter** a checagem de dependência do ACF Pro (feita antes do pivot) — o plugin deixa de exigir ACF Pro. `Plugin::boot()` passa a chamar diretamente o wiring dos módulos, sem checar `ACF_PRO`.
- [ ] Hooks de ativação/desativação (`register_activation_hook` / `register_deactivation_hook`) — flags + flush de rewrite rules
- [ ] `uninstall.php` — limpar options, meta de CPTs (perguntar ao utilizador), transients

**Teste:** plugin ativa sem checar ACF Pro nenhum, sem erros; desativação/ativação não deixam nada pendurado.

---

## Fase 2 — Motor de Field Types

O bloco fundamental: cada tipo de campo é uma classe PHP que sabe (a) como se comporta no editor (spec para o React), (b) como sanitizar o valor guardado, (c) qual o valor por omissão.

- [ ] `src/Fields/FieldType.php` — classe abstrata/contrato: `sanitize( $value )`, `default_value()`, `to_js_schema()` (array simples que o `edit.js` genérico consome para saber que tipo de input renderizar)
- [ ] `src/Fields/FieldTypeRegistry.php` — regista tipos disponíveis; filtro `gs_register_field_type` para extensibilidade futura
- [ ] Tipos simples (baixa complexidade, primeiro):
  - [ ] `Text`
  - [ ] `Textarea`
  - [ ] `TrueFalse`
  - [ ] `Radio`
  - [ ] `ColorPicker` (usa `wp-color-picker` nativo do WP)
  - [ ] `Tab` (não guarda valor — só agrupa visualmente os campos seguintes)
- [ ] Tipos médios:
  - [ ] `Link` (url + texto + target, reaproveita `wp_kses`/`esc_url`)
  - [ ] `Image` (guarda ID do attachment; usa `wp.media` no editor)
  - [ ] `Wysiwyg` (usa `RichText` do `@wordpress/block-editor` nos blocos; `wp_editor()` nativo em meta boxes/options)
- [ ] Tipo complexo (por último, é o mais trabalhoso):
  - [ ] `Repeater` — sub-campos aninhados, adicionar/remover/reordenar linhas. Decidir já: limite de profundidade (ex.: repeater não pode conter repeater, para simplificar) — perguntar ao utilizador se aceita essa limitação.

**Teste por tipo:** um pequeno harness de teste (PHPUnit, sem WP) que instancia o `FieldType`, passa valores válidos/inválidos a `sanitize()`, confirma o resultado. Para os que têm componente JS, teste manual: inserir o campo num bloco de teste, confirmar que grava e recarrega o valor certo.

---

## Fase 3 — Motor de Field Groups

- [ ] Formato JSON próprio para field groups (pasta `field-groups/` no plugin, substitui `acf-json/`) — schema simplificado inspirado no teu `acf-json/` original: `key`, `title`, `fields[]` (cada um com `type`, `name`, `label`, `default`, sub-opções específicas do tipo), `location[]` (regras: `block === acf/hero`, `post_type === project`, `options_page === growskills-extra`)
- [ ] `src/Fields/FieldGroupLoader.php` — faz `glob()` da pasta `field-groups/`, faz parse do JSON, valida contra os `FieldType` registados (erro claro se um field group referenciar um tipo inexistente)
- [ ] `src/Fields/LocationResolver.php` — dado o contexto atual (nome do bloco a renderizar, post type do ecrã, slug da options page), devolve quais field groups se aplicam
- [ ] Migrar o conteúdo dos 7 field groups existentes (`acf-json/*.json`) para o novo formato — **um de cada vez**, confirmando visualmente que os campos e valores por omissão batem certo com o original

**Teste:** um pequeno script/endpoint de debug que imprime, para um bloco/post type/options page dado, quais field groups o `LocationResolver` devolve — confirmar contra os 7 originais.

---

## Fase 4 — Blocos Gutenberg nativos

Um bloco de cada vez, pela mesma ordem do checklist original (hero, cta, body, headerimage, overview, default-block):

- [ ] `block.json` nativo (sem `"acf": {...}`), com `attributes` gerados a partir do field group correspondente
- [ ] `edit.js` genérico — um único componente React reutilizado por todos os blocos, que lê a spec do field group (via `FieldType::to_js_schema()`, exposta por REST ou inline no `block.json`) e renderiza `InspectorControls`/`RichText`/`MediaUpload` automaticamente. **Não escrever um `edit.js` à mão por bloco** — isso anularia a vantagem de ter um motor de campos.
- [ ] `render.php` — lê `$attributes` (em vez de `get_field()`), mantém o HTML/CSS visual idêntico ao bloco original do tema
- [ ] `preview.jpg` em todos os blocos (incluindo `default-block`, que não tinha)
- [ ] Categoria de bloco própria no editor (`block_categories_all`)

**Teste por bloco:** inserir no editor, preencher campos mínimos, gravar, recarregar a página, HTML do frontend bate certo com o `render.php` original do tema.

---

## Fase 5 — Custom Post Types com meta boxes nativas

- [ ] Migrar `Project` CPT para `src/PostTypes/Project.php`, capability_type dedicado, rewrite configurável
- [ ] `src/Fields/MetaBoxRenderer.php` — lê o field group associado ao post type (`location: post_type === project`) e gera uma meta box nativa (`add_meta_box`) com os inputs certos, guarda em `post meta` no `save_post`, com nonce + sanitização por `FieldType::sanitize()`

**Teste:** criar um Project de teste, preencher os campos na meta box, gravar, confirmar valores em `post meta` (via `wp postmeta list` no wp-cli ou inspeção direta na BD); permalink correto; utilizador só com `edit_posts` não consegue editar Projects.

---

## Fase 6 — Options Page nativa

- [ ] `src/Fields/OptionsPageRenderer.php` — página de admin (`add_options_page` ou `add_menu_page`) que lê o field group `options_page === growskills-extra`, gera o formulário, guarda em `wp_options` via `register_setting`

**Teste:** preencher a options page, gravar, confirmar valor em `wp option get growskills_extra` (ou equivalente); um bloco que consome essa opção reflete a mudança no frontend.

## Fase 6-B — Field Group Builder (UI de admin, decisão confirmada: SIM)

> **Confirmado pelo utilizador em 2026-08-14.** Tela de admin tipo "Custom Fields → Add New" do ACF: criar field groups e campos visualmente, sem tocar em JSON. Isto é um sub-produto dentro do plugin — trata-se por fases próprias, executadas com a mesma disciplina de "um item de cada vez, testa, aprova" do resto do checklist.

- [ ] `src/Admin/FieldGroupListTable.php` — ecrã "Growsfields → Field Groups", lista os field groups existentes (lidos do `FieldGroupLoader` da Fase 3), com Editar/Duplicar/Eliminar
- [ ] `src/Admin/FieldGroupEditScreen.php` — ecrã "Add New" / "Edit": campos base do grupo (título, `location` — via dropdowns em vez de JSON manual: escolher bloco/post type/options page)
- [ ] Componente React `field-group-builder` — lista de campos do grupo, com:
  - [ ] Adicionar campo (escolher tipo, nome, label, opções específicas do tipo)
  - [ ] Reordenar campos (drag handle, sem drag-and-drop "a sério" na primeira versão — usar botões subir/descer é aceitável para v1, avaliar drag real como refinamento posterior)
  - [ ] Editar/remover campo existente
  - [ ] Suporte a sub-campos dentro de `Repeater` (UI aninhada — o mais complexo desta fase)
- [ ] Guardar: ao gravar, o builder escreve o mesmo formato JSON da Fase 3 para `field-groups/*.json` (via REST endpoint próprio do plugin, com nonce + capability check) — ou seja, **a UI é só uma camada em cima do motor da Fase 3, não um sistema de dados paralelo**
- [ ] Validação: não deixar gravar um campo sem `name` único dentro do grupo; aviso claro se o `name` colidir

**Teste:** criar um field group novo do zero só pela UI (sem tocar em ficheiros), confirmar que aparece corretamente formatado em `field-groups/`, e que um bloco/CPT/options page consegue consumi-lo através do `LocationResolver` (Fase 3) sem alterações de código.

---

## Fase 7 — Segurança (herda do checklist original, ainda válida)
- [ ] ABSPATH guard em todos os ficheiros PHP
- [ ] Nonce no `ajax_load_more`
- [ ] Corrigir `$meta_query` indefinida
- [ ] Sanitizar `post_type` (whitelist), `posts_per_page` (teto), `offset` (`absint`)
- [ ] Corrigir `array_reduce()` sem valor inicial
- [ ] Rever escaping em todos os `render.php`
- [ ] Nonce + sanitização + capability check em toda a superfície nova da Fase 2-6 (meta boxes, options page, REST se vier a existir)

## Fase 8 — Performance
- [ ] Carregamento condicional de assets por bloco (`has_block`)
- [ ] Cache de queries pesadas
- [ ] Lazy loading consistente

## Fase 9 — Flexibilidade
- [ ] Text domain único, `load_plugin_textdomain`
- [ ] Hooks (`gs_block_categories`, `gs_enabled_post_types`, `gs_register_field_type`, etc.)
- [ ] Página de opções do próprio plugin (distinta da options page de conteúdo da Fase 6)

## Fase 10 — Testes automatizados
- [ ] PHP_CodeSniffer (WordPress-Extra)
- [ ] phpstan
- [ ] PHPUnit com wp-phpunit — cobertura extra aqui: `FieldType::sanitize()` de cada tipo, `LocationResolver`, `FieldGroupLoader`

## Fase 11 — Deploy no GitHub
- [x] Repositório criado (`growsfields`, https://github.com/IassineIahaia/growsfields)
- [ ] `readme.txt` com changelog e requisitos mínimos
- [ ] Push da branch `main`
- [ ] Tag/release `v1.0.0`
- [ ] (Opcional) GitHub Actions
- [ ] Teste final: clone limpo, `composer install && npm install && npm run build`, ativar num WP limpo **sem ACF Pro instalado**, confirmar que os 6 blocos, o CPT e a options page funcionam sem nenhum passo manual extra
