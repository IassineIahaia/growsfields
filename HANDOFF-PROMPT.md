# PROMPT UNIVERSAL — Continuação do projeto "Growsfields"

Cola isto inteiro como primeira mensagem numa nova conversa/IA para retomar o trabalho exatamente de onde ficou.

---

## APEL

Age como um engenheiro WordPress sénior a construir, do zero, o plugin **Growsfields** — um plugin próprio que reimplementa um motor de campos personalizados (inspirado no ACF Pro, mas **sem depender dele**), incluindo blocos Gutenberg nativos, custom post types, uma options page, e uma UI de admin visual para criar/editar esses campos. O objetivo final é ter o plugin versionado e publicado no GitHub, pronto a instalar em qualquer projeto, sem exigir nenhum plugin de terceiros.

## CONTEXTO IMPORTANTE — como chegámos até aqui

O projeto começou como "extrair os blocos ACF Pro do meu starter theme para um plugin próprio, mas continuando a depender do ACF Pro" (seguindo um checklist original, `plugin-blocos-checklist.md`). A meio da Fase 1, o utilizador esclareceu que o objetivo real era **não depender do ACF Pro de todo** — construir o próprio motor de campos personalizados dentro do plugin. Isso gerou um pivot de arquitetura e um checklist novo, `plugin-blocos-checklist-v2.md`, que é **o que está em vigor agora** (o `plugin-blocos-checklist.md` original fica só como referência histórica, não é a fonte da verdade).

Levantámos os field groups reais do tema original (`acf-json/`) e confirmámos que usam pelo menos **10 tipos de campo**: `text`, `textarea`, `wysiwyg`, `image`, `link`, `radio`, `true_false`, `color_picker`, `repeater`, `tab`.

O utilizador confirmou explicitamente que quer também uma **UI de admin visual** para criar/editar field groups (tipo a tela "Custom Fields" do próprio ACF, sem tocar em JSON manualmente) — isto é a Fase 6-B do checklist v2, e está confirmada, não é opcional.

**Atualização de âmbito (2026-08-17):** o utilizador confirmou que o objetivo não é só suportar os 10 tipos já em uso — é **paridade de funcionalidades com o ACF Pro completo**. Isto inclui:
- **Todos os ~30 tipos de campo do ACF Pro**, não só os 10 confirmados no conteúdo existente. Lista de referência (agrupada como no próprio ACF): Basic — Text, Textarea, Number, Range, Email, URL, Password; Content — Image, File, WYSIWYG Editor, oEmbed, Gallery; Choice — Select, Checkbox, Radio Button, Button Group, True/False; Relational — Link, Post Object, Page Link, Relationship, Taxonomy, User; jQuery — Google Map, Date Picker, Date Time Picker, Time Picker, Color Picker; Layout — Message, Tab, Group, Repeater, Flexible Content, Clone.
- **Conditional Logic** (mostrar/esconder campos com base no valor de outros campos).
- **Location Rules completas** (tipo o motor de regras do ACF: post type, page template, post status, user role, etc., combináveis com AND/OR).
- **Clone field** (reutilizar um field group ou campos individuais dentro de outro).
- **Export/Import de field groups** em PHP e JSON, tal como a UI nativa do ACF.

Isto substitui a limitação anterior "só 10 tipos, não precisa dos ~30" — essa frase ficava no `plugin-blocos-checklist-v2.md` original e deve ser corrigida lá também quando esse ficheiro for revisto. O plugin continua a **não depender do ACF Pro** — é uma reimplementação própria com o mesmo alcance de funcionalidades, não um wrapper.

**Incidente de perda de dados e recuperação (2026-08-17):** a IA sugeriu, por engano, correr `wp plugin uninstall growsfields` para testar o `uninstall.php`. Esse comando **não corre só o `uninstall.php`** — em WordPress, "uninstall" via WP-CLI (tal como "Apagar" no wp-admin) desativa, corre o `uninstall.php`, e **depois apaga todo o diretório do plugin do disco**, incluindo o `.git` inteiro. Como o repositório nunca tinha sido feito `push` para o GitHub (Fase 11), perdeu-se o histórico de commits por completo. O utilizador restaurou os ficheiros a partir de um backup manual antigo (anterior a essa perda), que recuperou todo o código-fonte intacto — só o `uninstall.php` (ainda não commitado) e o histórico do `git` em si ficaram por recuperar. O `uninstall.php` foi recriado a partir do registo da conversa. O `.git` foi reinicializado do zero (`git init` + reconectar `origin`), com um único commit de recuperação — **já não há o histórico item-a-item anterior**. Lição registada em memória para nunca mais sugerir comandos de "uninstall"/"delete" de plugins WordPress contra o diretório de trabalho real sem isolar o teste primeiro.

## REGRAS INQUEBRÁVEIS (mantidas do processo original, continuam a valer)

1. **Um item de cada vez.** Nunca implementar dois itens do checklist ao mesmo tempo.
2. **Testar antes de avançar.** Depois de cada item, correr/descrever um teste concreto e mostrar o resultado real.
3. **Perguntar antes do próximo item.** No fim de cada item testado, parar e perguntar explicitamente se pode avançar. Não continuar sem "sim"/"ok"/"avança".
4. **Se um teste falhar, não avançar.** Corrigir, testar outra vez, só depois pedir aprovação.
5. **Manter `PROGRESS.md`** na raiz do plugin, atualizado a cada item concluído.
6. **Commits pequenos e descritivos, em inglês.** Um item aprovado = um commit. Não acumular várias mudanças num commit só.
7. **Nunca inventar ficheiros ou decisões de arquitetura.** Perguntar antes de assumir nomes de prefixo, formatos, ou decisões que são do utilizador.
8. **Segurança não é opcional em nenhuma fase.** Nonce, sanitização, capability check, output escaping em qualquer handler novo (AJAX, REST, formulários admin), mesmo em código "temporário".
9. **Comentários de código e commits em inglês** (decisão tomada a meio da Fase 0/1). A conversa com o utilizador continua em português.
10. **Cuidado com BOM UTF-8:** ficheiros criados via PowerShell (`Out-File -Encoding utf8` ou `Set-Content -Encoding utf8`) ficam com um Byte-Order-Mark que parte `composer.json` (JSON inválido) e ficheiros PHP com `namespace` como primeira instrução (fatal error "Namespace declaration statement has to be the very first statement"). **Sempre** que um ficheiro for criado/editado via PowerShell, remover o BOM a seguir:
   ```powershell
   $content = Get-Content -Raw -Encoding UTF8 caminho\do\ficheiro
   [System.IO.File]::WriteAllText("$PWD\caminho\do\ficheiro", $content, [System.Text.UTF8Encoding]::new($false))
   ```

## FORMATO DE CADA ITERAÇÃO

Para cada item do checklist, seguir sempre esta estrutura na resposta:

```
### Item: [nome do item]
**Faz parte da fase:** [nº e nome da fase]

**O que vou implementar:**
[1-3 frases]

**Código/alterações:**
[implementação + comandos PowerShell exatos para o utilizador correr]

**Teste que corri:**
[passos manuais exatos + resultado esperado — ver nota sobre ambiente abaixo]

**Resultado:** ✅ passou / ❌ falhou (e o que corrigi)

**PROGRESS.md atualizado:** sim

**Posso avançar para: [próximo item]?**
```
Não escrever nada depois disto — esperar a resposta do utilizador.

## AMBIENTE DE DESENVOLVIMENTO DO UTILIZADOR (importante para dar instruções corretas)

- **SO:** Windows, PowerShell.
- **WordPress local:** Local by WP Engine ("Local"), site chamado `iassine-starter-theme`, URL `iassine-starter-theme.local`.
- **Pasta do plugin (raiz do repositório Git, único sítio de trabalho):**
  `C:\Users\Iassine\Local Sites\iassine-starter-theme\app\public\wp-content\plugins\growsfields`
- **PHP/Composer/WP-CLI só estão disponíveis dentro do "Site Shell" do Local** (botão no site, dentro da app Local) — o PowerShell normal do Windows/VS Code **não** tem `php`, `composer`, nem `wp` no PATH. Sempre indicar ao utilizador para abrir o Site Shell quando um comando precisar de PHP/Composer/WP-CLI.
- **`wp-content/mu-plugins/gs-debug.php`** é um ficheiro de debug temporário nosso (fora do plugin, carrega sempre, mesmo se o Growsfields estiver inativo) — usado para imprimir constantes/estado no wp-admin durante os testes. Atualizar o seu conteúdo consoante o que se está a testar em cada item.
- **GitHub:** repositório `https://github.com/IassineIahaia/growsfields`, remote `origin` já configurado, branch `main`, ainda sem push (fica para a Fase 11).
- A IA responsável **não tem ambiente WordPress/PHP funcional no seu próprio sandbox** para correr testes reais — os testes são sempre feitos pelo utilizador no ambiente acima, colando prints/output de volta. A IA deve escrever o código E o passo-a-passo exato de teste, mas não pode "provar" sozinha; depende da confirmação do utilizador antes de marcar ✅.

## DECISÕES JÁ TOMADAS (não voltar a perguntar)

| Decisão | Valor |
|---|---|
| Nome do plugin / slug | `growsfields` |
| Prefixo de funções/hooks/classes | `gs_` |
| Text domain | `growsfields` |
| Namespace PHP (PSR-4) | `Growsfields\` → pasta `src/` |
| Depende do ACF Pro? | **Não** — motor de campos próprio, mas com paridade total de funcionalidades (ver nota de âmbito acima) |
| UI de admin para criar campos (Fase 6-B)? | **Sim, confirmado** |
| Repeater aceita aninhamento (repeater dentro de repeater)? | **Não, confirmado 2026-08-17.** O field group real "Menu's" (`acf-json`) tem repeater aninhado — vai ser **reestruturado** na Fase 3 (migração dos 7 grupos), não vamos suportar aninhamento no motor. |
| Push para o GitHub | Feito **mais cedo que o planeado** (logo após o incidente de perda de dados, 2026-08-17), como salvaguarda — já não é preciso esperar pela Fase 11 para isso especificamente (mas as outras tarefas da Fase 11 — readme.txt, tag v1.0.0, etc. — continuam por fazer) |
| Commits/comentários de código | Inglês |
| Conversa com o utilizador | Português |
| Repositório GitHub | `IassineIahaia/growsfields`, remote já configurado, **com push feito e sincronizado** |

## FLUXO DE TRABALHO COM SUBAGENTS (adotado a partir da Fase 2, 2026-08-17)

O utilizador pediu explicitamente para dividir o trabalho por subagents "de forma profissional". Depois de testar paralelismo real (rejeitado) e sequencial-com-subagents (adotado), o padrão que está a funcionar bem é:

1. Para cada item do checklist, lançar **um único subagent de cada vez** (tool `Agent`, `subagent_type: "general-purpose"`) com um prompt exaustivo: contexto do projeto, ficheiros a ler primeiro, requisitos exatos, e instrução explícita para **não** tocar em `PROGRESS.md`/`HANDOFF-PROMPT.md` nem correr comandos git.
2. **Nunca confiar cegamente no relatório do subagent** — ler sempre os ficheiros que ele diz ter criado/alterado, diretamente, antes de apresentar ao utilizador. Já se encontraram bugs reais desta forma (ver nota sobre `FieldGroupLoader` abaixo).
3. Pedir ao subagent para correr `php -l` e um smoke test próprio (o binário PHP real deste ambiente, quando não há Site Shell à mão, está em `C:\Users\Iassine\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe` — nem `php` nem XAMPP estão no PATH normal). Um script de teste autónomo (fora do WP) precisa de `define('ABSPATH', ...)` antes de `require vendor/autoload.php` (todas as classes têm guard `if (!defined('ABSPATH')) exit;`) e stubs mínimos para `do_action`/`apply_filters`/`sanitize_text_field` etc.
4. Depois da verificação própria, apresentar o item ao utilizador no formato definido acima (com passos `wp eval-file`/`php -l` para o Site Shell dele), esperar confirmação, só depois marcar `PROGRESS.md` e fazer o commit.
5. Ficheiros de teste temporários (harnesses `wp eval-file`) são sempre apagados depois de confirmados — nunca ficam commitados.

**Nota importante:** um subagent já entregou um bug real (não um falso positivo de teste) — a resolução de `clone` no `FieldGroupLoader` não tratava recursivamente um clone aninhado dentro de um grupo clonado. Foi corrigido diretamente (sem novo subagent) depois de identificado na revisão. Continuar a rever com este nível de detalhe, não só confiar no "smoke test passou" do relatório do subagent.

## ONDE PARÁMOS (estado exato, fim do dia 2026-08-18)

**Fase 1 — Fundação do plugin:** ✅ **completa**.

**Fase 2 — Motor de Field Types:** ✅ **completa** — 34 tipos de campo (paridade total ACF Pro).

**Fase 3 — Motor de Field Groups:** ✅ **COMPLETA** (fechada em 2026-08-18):
- [x] Schema JSON (`field-groups/SCHEMA.md`)
- [x] `FieldGroupLoader`
- [x] `LocationResolver` (`src/Fields/LocationResolver.php`) — 21 testes próprios. Decisão do prefixo `acf/`→`growsfields/` resolvida na migração (ver abaixo, não precisou de normalização em runtime).
- [x] `ConditionalLogicEngine` (`src/Fields/ConditionalLogicEngine.php`) — 34 testes próprios. Defaults invertidos face ao `LocationResolver` (documentado no docblock): `[]`=sempre visível, `[[]]`=verdade vácua. **Decisão ainda em aberto, mesma natureza do prefixo de bloco:** se um caller da Fase 4/5/6 deve construir o `values map` só com os campos do próprio grupo, ou alargado a vários grupos — determina se a referência cross-group real (`field_67ed36dd41608`, no campo "Kies overzicht") alguma vez resolve. Ainda não decidido, não bloqueia nada por agora.
- [x] Migração dos 7 field groups reais (`field-groups/group_*.json`) — prefixo `acf/*`→`growsfields/*` aplicado, "Menu's" (`group_67bc28be09501`) reestruturado de `repeater` aninhado para `flexible_content` com um único layout `"menu"` (decisão do utilizador, 2026-08-18) contendo `menu_title` (text) + `menu_items` (repeater válido). `example-migrated-group.json` removido (era um nome de ficheiro de exemplo, não seguia a convenção `group_<key>.json`).

**Fase 4 — Blocos Gutenberg nativos**, EM CURSO:
- [x] Categoria de bloco "Growsfields" registada via `block_categories_all`
- [x] `src/Blocks/BlockLoader.php` — infraestrutura de registo genérica: para cada `blocks/{slug}/block.json`, resolve os field groups aplicáveis via `LocationResolver`+`FieldGroupLoader`, calcula `attributes` do WP a partir da forma real de `default_value()` de cada campo (sem tabela paralela por tipo), regista via `register_block_type()` no hook `init`, despacha o render para `blocks/{slug}/render.php`. **Decisão confirmada pelo utilizador (2026-08-18):** a função do tema `get_block_classes()` (usada por body/cta/overview) depende do ACF (`get_field()`) — reimplementada nativamente no plugin como `gs_block_classes()` (`src/Blocks/block-render-helpers.php`, função global não-namespaced, `require_once` direto a partir de `growsfields.php`), lendo `$attributes` em vez de `get_field()`. Porto fiel byte-a-byte (incluindo a redundância `no-margin with-margin-none`).
- [x] `hero` (`blocks/hero/render.php`) — 14 testes próprios. `align_image` confirmado como código morto nos dados reais (nunca existiu em nenhum dos 7 grupos) e **descartado** na reimplementação (não inventado como novo campo).
- [x] `cta` (`blocks/cta/render.php`) — 20 testes próprios (partilhados com body/default-block), confirma o merge automático com "Block options".
- [x] `body` (`blocks/body/render.php`) — idem.
- [x] `default-block` (`blocks/default-block/render.php`) — confirmado ser um **scaffold de desenvolvimento** tanto no tema real como aqui: nenhum field group (nem `acf-json/` original, nem `field-groups/` migrado) alguma vez visou `acf/default-block`/`growsfields/default-block` — só recebe os campos partilhados do "Block options" (via wildcard `all`), nunca renderiza nada em produção. Documentado no topo do ficheiro, não é um bug.
- [ ] **`headerimage` — PRÓXIMO ITEM.** Simples: só o campo `header_image` (image) + a classe condicional `align-image-*`, que também é código morto (mesma razão do Hero — `align_image` nunca existe nos dados reais). Sem merge de "Block options" (grupo exclui explicitamente `headerimage` e `hero` via `block != growsfields/headerimage`).
- [ ] `overview` — o mais complexo: usa `WP_Query` e `get_template_part()` para `includes/overview-item-{tipo}.php` / `includes/overview-more.php` (ainda não investigados a fundo — fazer isso antes de implementar). Deixar para depois do `headerimage`.
- [ ] `edit.js` genérico — só depois dos 6 `render.php` estarem prontos. **Nota importante:** sem `edit.js`, os blocos registados só via PHP **não aparecem no inserter visual** do editor — para testar cada bloco antes disso, colar manualmente o comentário do bloco no "Code editor" do wp-admin (ver exemplo dado ao utilizador na sessão de 2026-08-18 para o Hero) e ver o resultado no frontend.

**Nota sobre o `Image` field (relevante para qualquer bloco futuro):** ao contrário do campo ACF original (`return_format: "url"`), o nosso `Image::sanitize()`/`default_value()` guardam sempre o attachment ID (int), nunca a URL — todo `render.php` que usa uma imagem tem de resolver com `wp_get_attachment_image_url( $id, 'full' )` e tratar o caso de attachment apagado (`wp_get_attachment_image_url()` devolve `false`).

**Ícone do plugin:** ✅ feito, cosmético.

**Lembrete do utilizador (2026-08-17, continua válido):** validar tudo contra o **tema real** `starter-2026-iassine`. Ainda não foi feito nenhum teste manual no browser real — todos os itens da Fase 4 até agora só têm testes PHP isolados (smoke tests com stubs de funções WP), confirmados pelo próprio utilizador como suficientes para continuar sem esperar pelo teste manual a cada item (decisão explícita, 2026-08-18, no item do Hero). Vale a pena fazer uma passagem manual real no Local assim que a Fase 4 tiver mais blocos prontos.

## PARA ONDE VAMOS (checklist completo)

Ver o ficheiro anexo/fornecido `plugin-blocos-checklist-v2.md` para o roadmap completo e detalhado (Fases 0 a 11). Resumo das fases seguintes após terminar a Fase 1:

- **Fase 2** — ✅ **Completa.** Motor de Field Types, com paridade total ao ACF Pro (34 tipos implementados e testados).
- **Fase 3** — ✅ **Completa (2026-08-18).** Motor de Field Groups: `FieldGroupLoader`, `LocationResolver`, `ConditionalLogicEngine`, migração dos 7 field groups originais.
- **Fase 4** — **Em curso.** Blocos Gutenberg nativos (hero ✅, cta ✅, body ✅, default-block ✅, headerimage próximo, overview depois) com `edit.js` genérico dirigido pela definição do field group (último item da fase)
- **Fase 5** — CPT Project com meta boxes nativas
- **Fase 6** — Options page nativa
- **Fase 6-B** — Field Group Builder: UI de admin em React para criar/editar campos visualmente, com Conditional Logic, Location Rules completas, Clone field e Export/Import PHP/JSON (confirmado, não opcional, paridade total com a tela "Custom Fields" do ACF Pro)
- **Fase 7** — Segurança (herda os bugs do checklist original: nonce, sanitização, escaping)
- **Fase 8** — Performance
- **Fase 9** — Flexibilidade (hooks, i18n)
- **Fase 10** — Testes automatizados (phpcs, phpstan, PHPUnit)
- **Fase 11** — Deploy no GitHub (push, tag v1.0.0, teste de clone limpo sem ACF Pro instalado)

## FICHEIROS QUE DEVES PEDIR AO UTILIZADOR PARA ANEXAR NESTA NOVA CONVERSA

1. `plugin-blocos-checklist-v2.md` (roadmap completo e autoritativo)
2. `PROGRESS.md` (estado atual, ficheiro vivo)
3. Opcionalmente, o `plugin-blocos-checklist.md` original, só para contexto histórico do pivot

## SE ALGO NÃO ESTIVER CLARO

Perguntar ao utilizador antes de assumir — não inventar decisões de arquitetura (formato exato do JSON de field groups, se o Repeater aceita aninhamento de outro Repeater, limites de profundidade, etc.). Essas são decisões dele, não da IA.
