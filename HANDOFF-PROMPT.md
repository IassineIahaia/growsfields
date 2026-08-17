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

## ONDE PARÁMOS (estado exato, fim do dia 2026-08-17)

**Fase 1 — Fundação do plugin:** ✅ **completa** (incluindo `uninstall.php`, testado em isolamento via `wp eval-file` depois do incidente de perda de dados).

**Fase 2 — Motor de Field Types:** ✅ **completa** — 34 tipos de campo (paridade total ACF Pro), `FieldType` abstrato, `FieldTypeRegistry` com filtro `gs_register_field_type`.

**Fase 3 — Motor de Field Groups**, em curso:
- [x] Schema JSON (`field-groups/SCHEMA.md`, confirmado pelo utilizador)
- [x] `FieldGroupLoader` (`src/Fields/FieldGroupLoader.php`) — testado, incluindo a correção do bug de clone aninhado
- [ ] **`LocationResolver` — EM CURSO, subagent lançado, ainda sem resposta quando a sessão parou.** Vai resolver, para um contexto dado (bloco/post_type/options_page/etc.), quais field groups se aplicam, usando as regras `location` (OR-de-AND) do schema. **Ao retomar: verificar se a notificação do subagent já chegou; se sim, rever o ficheiro `src/Fields/LocationResolver.php` a fundo (ver fluxo de trabalho acima) antes de apresentar ao utilizador. Se não, esperar ou relançar.** O subagent foi avisado para sinalizar como decisão a rever: a incompatibilidade entre `location.block` nos dados reais (ainda usa prefixo antigo `acf/...`, ex. `acf/overview`) e os novos blocos nativos registados como `growsfields/...` (Fase 4 prep) — **decidir isto com o utilizador antes de avançar**.
- [ ] Conditional Logic engine (avaliação das regras já guardadas em `FieldType::get_conditional_logic()` — ainda só round-trip, nada avalia)
- [ ] Migrar os 7 field groups reais (`acf-json/` do tema) para o novo formato — **incluindo reestruturar "Menu's"** para não usar repeater aninhado (decisão já tomada, ver tabela acima)

**Fase 4 — Blocos Gutenberg**, preparação feita (fora do checklist formal, adiantado com autorização do utilizador):
- [x] `blocks/{hero,cta,body,headerimage,overview,default-block}/block.json` — só metadata estática (nome `growsfields/{slug}`, título, ícone, categoria), **sem `attributes`** (depende do schema da Fase 3)
- [x] Categoria de bloco "Growsfields" registada via `block_categories_all`
- [ ] Tudo o resto (attributes, `edit.js` genérico, `render.php`, os 6 blocos completos) fica para quando a Fase 3 estiver fechada

**Ícone do plugin:** ✅ feito (`assets/icon.svg`, `assets/admin-icon.svg`) — cosmético, não é item do checklist.

**Lembrete do utilizador (2026-08-17):** o objetivo final é validar tudo isto contra o **tema real** `starter-2026-iassine` (`wp-content/themes/starter-2026-iassine/`), de onde vieram os `acf-json` originais — as Fases 3-5 devem ser testadas contra esse tema, não só smoke tests isolados.

## PARA ONDE VAMOS (checklist completo)

Ver o ficheiro anexo/fornecido `plugin-blocos-checklist-v2.md` para o roadmap completo e detalhado (Fases 0 a 11). Resumo das fases seguintes após terminar a Fase 1:

- **Fase 2** — ✅ **Completa.** Motor de Field Types, com paridade total ao ACF Pro (34 tipos implementados e testados).
- **Fase 3** — Motor de Field Groups (parser JSON próprio, `FieldGroupLoader`, `LocationResolver` com regras combináveis AND/OR, Conditional Logic engine, migração dos 7 field groups originais)
- **Fase 4** — Blocos Gutenberg nativos (hero, cta, body, headerimage, overview, default-block) com `edit.js` genérico dirigido pela definição do field group
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
