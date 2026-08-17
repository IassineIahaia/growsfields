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
| Depende do ACF Pro? | **Não** — motor de campos próprio |
| UI de admin para criar campos (Fase 6-B)? | **Sim, confirmado** |
| Commits/comentários de código | Inglês |
| Conversa com o utilizador | Português |
| Repositório GitHub | `IassineIahaia/growsfields`, remote já configurado |

## ONDE PARÁMOS (estado exato)

**Fase 1 — Fundação do plugin**, itens concluídos e testados (✅):
- [x] Header do plugin (`growsfields.php`)
- [x] Constantes (`GS_PATH`, `GS_URL`, `GS_VERSION`)
- [x] Composer autoload PSR-4 (`composer.json`, `src/Plugin.php`, `vendor/autoload.php` gerado via `composer install` — perdido no incidente de 2026-08-17, regenerar)
- [x] Checagem de dependência do ACF Pro implementada, testada, **e depois revertida** (o plugin já não depende do ACF Pro) — ambos os estados foram testados e confirmados a funcionar
- [x] Hooks de ativação/desativação (`register_activation_hook`/`register_deactivation_hook`) — testado e confirmado: `wp option get gs_version` devolveu `0.1.0`, `wp option get gs_activated_at` devolveu um timestamp válido, ambos gravados corretamente na ativação

**Item em curso, código recriado após o incidente de perda de dados de 2026-08-17, a aguardar novo teste:**
- [ ] **`uninstall.php`** — último item da Fase 1. Conteúdo: guarda `WP_UNINSTALL_PLUGIN`, `delete_option( 'gs_version' )`, `delete_option( 'gs_activated_at' )`. Já tinha sido testado uma vez com sucesso (as options foram corretamente removidas), mas o teste em si (`wp plugin uninstall growsfields`) apagou todo o diretório do plugin incluindo o `.git` — ver nota do incidente acima. **Não repetir esse comando.** Para re-testar em segurança: copiar a pasta do plugin para um local temporário fora do repositório git e correr o uninstall só lá, OU usar `wp eval-file uninstall.php` depois de definir manualmente a constante `WP_UNINSTALL_PLUGIN`, nunca `wp plugin uninstall` nem "Apagar" no wp-admin contra a pasta real.

**Pendente antes de continuar para a Fase 2:**
- [ ] `composer install` (Site Shell) para regenerar `vendor/` (gitignored, perdido no incidente, mas reproduzível a partir do `composer.json` recuperado)
- [ ] `git init` + reconectar `origin` + commit único de recuperação (histórico item-a-item anterior perdido, ver nota do incidente)

**Nota pendente, não bloqueante:** a `Description:` no header do `growsfields.php` e no `composer.json` ainda menciona "ACF Pro" — desatualizado desde o pivot, por corrigir quando for conveniente.

## PARA ONDE VAMOS (checklist completo)

Ver o ficheiro anexo/fornecido `plugin-blocos-checklist-v2.md` para o roadmap completo e detalhado (Fases 0 a 11). Resumo das fases seguintes após terminar a Fase 1:

- **Fase 2** — Motor de Field Types, com paridade total ao ACF Pro (~30 tipos, ver lista na secção de âmbito acima). Ordem sugerida: primeiro os 10 já confirmados em uso (Text, Textarea, TrueFalse, Radio, ColorPicker, Tab, Link, Image, Wysiwyg, Repeater), depois os restantes tipos "simples" (Number, Range, Email, URL, Password, Select, Checkbox, Button Group, Message, File, Date Picker, Date Time Picker, Time Picker), depois os relacionais (Post Object, Page Link, Relationship, Taxonomy, User, Google Map, oEmbed, Gallery), e por último os de layout complexos (Group, Flexible Content, Clone)
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
