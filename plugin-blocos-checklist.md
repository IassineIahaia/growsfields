# Do starter theme ao teu próprio plugin de blocos (ACF Pro)

Analisei o teu `starter-2026-iassine` (functions.php, blocks/, includes/, acf-json/). Isto é o roteiro completo — passo a passo, com os bugs reais que encontrei no código, e o que falta para ficar sólido em segurança, performance e flexibilidade.

---

## 0. O que já tens (mapeamento rápido)

| No tema hoje | Vira no plugin |
|---|---|
| `blocks/*` (hero, cta, body, headerimage, overview, default-block) + `block.json` + `render.php` | Módulo `Blocks` do plugin, auto-registados por `glob()` |
| `acf-json/*.json` | Pasta `acf-json/` do plugin, com `acf/settings/save_json` e `acf/settings/load_json` apontados para lá |
| `includes/post-types.php` | Módulo `PostTypes` |
| `includes/image-optim.php`, `focus-point.php`, `wcag.php`, `role-restrictions.php`, `blocks-css-classes.php`, `clean-up.php` | Classes separadas dentro do plugin, cada uma com o seu namespace |
| Registo de blocos via `allowed_block_types_all` + `glob(blocks/*)` | Mantém a lógica, mas com fallback caso a pasta esteja vazia |
| CSS/JS do tema (`css/*.css`, `js/script.js`) | Fica **no tema** (é apresentação) — só o CSS/JS *específico dos blocos* migra para o plugin |

**Regra de ouro:** o plugin trata de *dados e estrutura* (CPTs, campos, blocos, segurança, performance). O tema trata de *aparência* (CSS, layout geral, menus). Isto é o que te permite reutilizar o plugin em vários temas/clientes.

---

## 1. Checklist passo a passo (do zero)

### Fase 1 — Fundação do plugin
- [ ] Criar `meu-plugin-blocos/meu-plugin-blocos.php` com header padrão (Plugin Name, Version, Text Domain, Requires PHP, Requires at least)
- [ ] Definir constantes: `MPB_PATH`, `MPB_URL`, `MPB_VERSION`
- [ ] Autoload via `composer.json` (PSR-4) — evita `require_once` manual espalhado
- [ ] Estrutura de pastas:
  ```
  meu-plugin-blocos/
  ├── meu-plugin-blocos.php
  ├── composer.json
  ├── acf-json/
  ├── src/
  │   ├── Blocks/
  │   ├── PostTypes/
  │   ├── Security/
  │   ├── Performance/
  │   ├── Admin/
  │   └── Plugin.php
  ├── blocks/               (block.json + render.php, um por bloco)
  ├── assets/
  │   ├── src/
  │   └── build/
  ├── languages/
  ├── tests/
  └── readme.txt
  ```
- [ ] **Checagem de dependência do ACF Pro**: usar `acf_get_setting` ou `class_exists('ACF')` no hook `plugins_loaded`; se não existir, desativar o plugin automaticamente e mostrar `admin_notice` claro. (Isto **não existe** no teu tema atual — hoje, se alguém desativa o ACF, o site simplesmente parte.)
- [ ] Hooks de ativação/desativação (`register_activation_hook` / `register_deactivation_hook`) — nada de lógica pesada aqui, só flags e flush de rewrite rules
- [ ] `uninstall.php` — limpar options, CPT data (opcional/perguntar ao utilizador), transients

### Fase 2 — Migrar os blocos ACF
- [ ] Copiar cada pasta de `blocks/` tal como está (`block.json` + `render.php` + `preview.jpg`)
- [ ] Corrigir a chave `render_callback` no `block.json` — está a apontar para `my_acf_block_render_callback`, uma função que **não existe em lado nenhum do teu código**. Como estás a usar `"acf": { "renderTemplate": "render.php" }`, o ACF já trata do render sozinho; a chave `render_callback` é redundante e confusa — remove-a de todos os 5 blocos.
- [ ] Registar os blocos com `register_block_type()` a partir de `glob($blocks_dir . '*')`, exatamente como já fazes, mas dentro de uma classe `Blocks\Loader`
- [ ] Garantir que **todos** os blocos têm `preview.jpg` (o `default-block` não tem — falha visual no inserter)
- [ ] Criar uma categoria de bloco própria (`register_block_type_args` ou `block_categories_all`) em vez de usar `formatting` — fica mais fácil de encontrar os teus blocos no editor
- [ ] Definir `postTypes` no `acf.postTypes` de cada `block.json` para limitar onde cada bloco pode ser usado (hoje está vazio `[]` = aparece em tudo, incluindo sítios onde não faz sentido)

### Fase 3 — Migrar os Field Groups (ACF-JSON)
- [ ] Copiar `acf-json/*.json` para dentro do plugin
- [ ] Registar os filtros de sincronização:
  ```php
  add_filter('acf/settings/save_json', fn() => MPB_PATH . 'acf-json');
  add_filter('acf/settings/load_json', function($paths) {
      $paths[] = MPB_PATH . 'acf-json';
      return $paths;
  });
  ```
- [ ] Prefixar as `name` dos campos com o teu namespace (ex.: `mpb_` ou `gs_`) para evitar colisão com outros plugins/temas que também usem ACF — hoje tens campos genéricos como `title`, `text`, `image` que podem colidir
- [ ] Documentar, por bloco, quais campos são **obrigatórios para o tema renderizar sem erro** (ex.: `hero` precisa de `image` + `title`, senão a secção nem aparece) — isto é o que torna os campos "compatíveis com o tema": o plugin garante a estrutura de dados, o tema consome-a

### Fase 4 — Migrar Custom Post Types e Taxonomias
- [ ] Mover `create_project_cpt()` para `PostTypes/Project.php`
- [ ] Adicionar `capability_type` dedicado (hoje usa `'post'`, o que significa que qualquer editor de posts edita Projetos — se quiseres controlo fino de permissões, precisa de capabilities próprias)
- [ ] Registar via `init` com prioridade explícita, e adicionar rewrite/slug configurável por filtro (para clientes que queiram `/projetos/` vs `/projects/`)
- [ ] Se vais reusar isto noutros projetos, torna o CPT **opcional** via `add_filter('mpb_enabled_post_types', ...)` — nem todo cliente quer "Projecten"

### Fase 5 — Migrar os includes de suporte
Cada ficheiro vira uma classe com um único hook de bootstrap:

- [ ] `image-optim.php` → `Performance\ImageOptimizer`
- [ ] `focus-point.php` → `Blocks\FocusPoint`
- [ ] `wcag.php` → `Accessibility\Wcag` (ver nota de limpeza abaixo)
- [ ] `role-restrictions.php` → `Admin\PageRestrictions`
- [ ] `blocks-css-classes.php` → `Blocks\ClassHelper`
- [ ] `clean-up.php` → mantém-se **no tema**, não no plugin (é um comportamento de "primeira instalação de tema" — `after_switch_theme` não faz sentido dentro de um plugin)

### Fase 6 — Build tooling
- [ ] `package.json` com scripts `build`, `watch`, `zip` (já tens uma base em `package.json` / `postcss.config.cjs` / `zip-theme.js` — replica o padrão para o plugin)
- [ ] `@wordpress/scripts` para compilar JS/CSS dos blocos (editor vs frontend separados)
- [ ] Versionar `assets/build/` fora do Git (gitignore) e gerar no CI

### Fase 7 — Segurança
- [ ] `if (!defined('ABSPATH')) exit;` no topo de **todos** os ficheiros PHP (só o `wcag.php` tem isto hoje — os restantes includes ficam acessíveis diretamente se alguém souber o caminho)
- [ ] **Corrigir `ajax_load_more()`**: hoje não tem nonce nenhum — qualquer pedido POST anónimo consegue correr esta função. Adicionar `check_ajax_referer('mpb_load_more', 'nonce')` e validar `nonce` vindo do `wp_localize_script`
- [ ] **Corrigir a variável `$meta_query` indefinida** em `ajax_load_more()` — está a usar `compact('post_type', 'posts_per_page', 'meta_query', 'offset', 'post_status')` mas `$meta_query` nunca é definida; isto gera um *PHP notice* e passa `null` para o `WP_Query`, sem controlo
- [ ] **Sanitizar `$_POST` em `ajax_load_more()`**: `post_type` deve passar por uma whitelist (`in_array($_POST['post_type'], $allowed_types)`), `posts_per_page` deve ter um teto (`min(intval($_POST['posts_per_page']), 50)`) e `offset` deve ser `absint()`. Hoje um pedido malicioso pode pedir `posts_per_page=999999` de qualquer post type público, incluindo `page` ou tipos privados mal configurados
- [ ] **Corrigir o `array_reduce()` em `functions.php`** — não tem valor inicial (`$initial`), o que causa erro de tipo em PHP moderno quando o array de blocos concatena strings sem ponto de partida. Adicionar `''` como terceiro argumento
- [ ] Escapar sempre saída (`esc_html`, `esc_url`, `esc_attr`) — os blocos que revi (`hero`) já fazem isto bem, mantém o padrão nos restantes
- [ ] `capability checks` em todos os handlers admin (`role-restrictions.php` já faz `current_user_can('manage_options')` corretamente — bom padrão a replicar)
- [ ] Nada de `eval()`, `extract()` de `$_POST`, ou `include` com caminho vindo do utilizador
- [ ] Desativar o editor de ficheiros no wp-admin: `define('DISALLOW_FILE_EDIT', true)` (documentar, não forçar — é decisão do cliente)
- [ ] Restringir REST API dos CPTs internos se não precisares deles publicamente (`show_in_rest` seletivo, `rest_base` explícito)
- [ ] Rate limiting básico no AJAX (transient por IP) se o site tiver tráfego alto

### Fase 8 — Performance
- [ ] Carregar CSS/JS de blocos **apenas quando o bloco está presente na página** (`has_block('acf/hero')`) em vez de sempre — hoje o `frontend.css` e `script.js` carregam globalmente
- [ ] Cachear queries pesadas (ex. `overview` block, `ajax_load_more`) com `wp_cache_get`/`set` ou `transient`, invalidando no `save_post`
- [ ] Confirmar que `image-optim.php` (already strong: WebP-friendly sizes, apaga original, `wp_editor_set_quality`) é replicado tal e qual — é um dos pontos mais fortes que já tens
- [ ] Adicionar `loading="lazy"` por defeito em todas as imagens de bloco que não sejam hero (já fazes isto em `theme_image()` — só falta garantir consistência nos `render.php` que não passam por essa função)
- [ ] Combinar/mininificar assets de blocos num único bundle por contexto (editor vs frontend)
- [ ] Medir com Query Monitor: nenhum bloco deve gerar mais de 1-2 queries extra (o `overview` e o `ajax_load_more` são os candidatos a rever primeiro)
- [ ] Adicionar suporte a object cache persistente (Redis/Memcached) como opção, não obrigatório

### Fase 9 — Flexibilidade
- [ ] Todos os textos fixos passam por `__()`/`_e()` com **um único** text domain consistente (hoje misturas `starter-theme` e `starter-2026` no mesmo projeto — escolhe um)
- [ ] `load_plugin_textdomain()` no boot do plugin, com pasta `languages/` e ficheiro `.pot`
- [ ] Expor hooks/filtros para quem for usar o plugin noutro tema:
  - `mpb_block_categories`
  - `mpb_enabled_post_types`
  - `mpb_image_sizes`
  - `mpb_excerpt_length`
- [ ] Página de configurações (ACF Options Page, já tens o padrão em `default-settings.php`) para: post types ativos, tamanhos de imagem, ativar/desativar módulos (WCAG, restrições de página, etc.)
- [ ] Tornar os `add_theme_support` (title-tag, html5, post-thumbnails) responsabilidade do **tema**, não do plugin — hoje estão em `default-settings.php`, que devia ficar no tema
- [ ] Versionamento semver + changelog (`readme.txt` estilo WordPress.org, mesmo que não vás publicar lá)

### Fase 10 — Testes
- [ ] **Segurança**: correr [WPScan](https://wpscan.com/) ou `wp-cli` `security-check` contra um site local; testar manualmente o endpoint AJAX com Postman sem nonce (deve falhar com 403)
- [ ] **Código**: PHP_CodeSniffer com `WordPress-Extra` + `WordPress-Docs` ruleset; `phpstan` nível 5+ para apanhar a variável `$meta_query` e afins antes de ires para produção
- [ ] **Unitários**: PHPUnit com [wp-phpunit](https://github.com/wp-phpunit/wp-phpunit) — cobrir pelo menos: registo de CPT, sanitização do AJAX, render de cada bloco com/sem campos preenchidos
- [ ] **Performance**: Lighthouse/PageSpeed em páginas com todos os blocos usados; Query Monitor para N+1 queries
- [ ] **Acessibilidade**: axe DevTools ou WAVE em cima de cada bloco individualmente (já tens `wcag.php` como base — falta testar de facto, não só declarar suporte)
- [ ] **Compatibilidade**: testar com ACF Free vs ACF Pro (falhar graciosamente se for Free e um bloco usar `repeater`/`flexible content`, que são Pro-only)
- [ ] **Multisite**: se algum cliente usar rede multisite, testar ativação em subsite vs rede toda

### Fase 11 — Distribuição
- [ ] Gerar `.zip` reprodutível via script (tens já `zip-theme.js` como referência — adapta para o plugin, excluindo `node_modules`, `tests`, `.git`)
- [ ] Mecanismo de update: GitHub privado + [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) é o caminho mais simples sem depender do WordPress.org
- [ ] `readme.txt` com changelog, requisitos mínimos (PHP, WP, ACF Pro version)
- [ ] Checklist de release: bump de versão em 3 sítios (header do plugin, constante `MPB_VERSION`, `readme.txt`) — considera um script que faz isto automaticamente para evitares desalinhamento

---

## 2. Pontos cegos que encontrei no código atual (bugs reais)

1. **`render_callback` inexistente** nos 5 `block.json` — chave morta/confusa, aponta para função que não existe em lado nenhum.
2. **AJAX sem nonce** (`ajax_load_more`) — qualquer visitante anónimo consegue chamar `wp_ajax_nopriv_load_more` sem token de segurança.
3. **`$meta_query` indefinida** dentro do `compact()` do AJAX — gera notice e passa dado não controlado para a query.
4. **`array_reduce()` sem valor inicial** em `functions.php` (excerto de blocos) — comportamento inconsistente/erro conforme a versão do PHP.
5. **`$_POST['post_type']` e `$_POST['posts_per_page']` sem sanitização nem whitelist** — risco de abuso de query (post types privados, paginação enorme).
6. **Falta de `ABSPATH` guard** na maioria dos `includes/*.php` (só `wcag.php` tem).
7. **Text domain inconsistente** (`starter-theme` vs `starter-2026`) — quebra a tradução.
8. **`default-block` sem `preview.jpg`** — inconsistência visual no inserter de blocos.
9. **Nenhuma checagem de dependência do ACF Pro** — se o plugin ACF for desativado, o site inteiro parte silenciosamente (fatal errors em `get_field()`).
10. **`svg()` em `functions.php`** lê o ficheiro sem verificar `file_exists()` — gera warning se o SVG não existir.
11. **Sem versionamento de cache-busting** consistente para todos os assets (alguns usam `filemtime()`, bom padrão — replica em todos).

## 3. O que ainda não existe e vale a pena adicionar

- **Painel de administração central** do plugin (ativar/desativar módulos: WCAG, restrições de página, image optim, etc.) em vez de tudo estar sempre ligado
- **Block patterns** (composições pré-montadas de vários blocos) para acelerar a criação de páginas pelos clientes
- **Suporte a Block Variations** (ex. `hero` com variante "imagem à esquerda" / "imagem à direita") em vez de campos condicionais dentro do mesmo bloco
- **Testes automatizados em CI** (GitHub Actions: lint + PHPUnit + build de assets a cada push)
- **Sistema de log/debug próprio** (`error_log` condicional a `WP_DEBUG`, hoje o `image-optim.php` já faz isto bem — generalizar)
- **Fallback quando ACF é só a versão Free** — pelo menos um aviso claro em vez de blocos partidos
- **Documentação interna** (um `docs/` com um exemplo de cada tipo de campo e como o `render.php` deve consumi-lo) — isto é o que te vai poupar tempo em cada novo projeto, porque a "compatibilidade com o tema" passa a ser um contrato documentado, não conhecimento tácito

---

## 4. Fluxo de trabalho recomendado

1. **Local**: `wp-env` ou Local/DevKinsta com ACF Pro ativo + o plugin em modo `WP_DEBUG=true`
2. **Staging**: sincroniza `acf-json/` (git) — nunca a base de dados de campos, sempre os ficheiros JSON, para evitar duplicação de field groups
3. **QA**: corre a checklist da Fase 10 antes de cada release
4. **Produção**: `WP_DEBUG=false`, cache de objeto ligada, `DISALLOW_FILE_EDIT` ativo
5. **Novo cliente/tema**: instala o plugin, ativa só os módulos/CPTs necessários via `mpb_enabled_post_types`, o tema consome os blocos e campos sem reescrever lógica

Isto transforma o que hoje está espalhado por um `functions.php` de 300+ linhas num sistema modular que sobrevive a trocas de tema — que é exatamente o problema que estás a tentar resolver.
