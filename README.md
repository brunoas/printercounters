# printercounters (fork)

SNMP-based printer counter management plugin for GLPI.

Fork of the [original printercounters](https://github.com/InfotelGLPI/printercounters) by [Infotel](https://blogglpi.infotel.com), updated for GLPI 11 compatibility and extended with new cartridge tracking and yield analysis features by [Bruno Andrade](https://github.com/brunoas).

> The original project has been inactive since version 2.0.2 and is not compatible with GLPI 11. This fork picks up from where it left off.

---

## What this fork adds

### 3.4.0 — Localized SNMP color labels, search column improvements and error date highlighting
- Localize SNMP color names in dropdown and search columns (pt_BR)
- Fix `save()` validation after `snmp_colors` array type change
- Show dash for cartridges with no toner consumption in all search columns
- Consistent canonical color ordering (KCMY) across toner, coverage and date columns
- Single-toner printers show value only; multi-toner show "value (Color)"
- Last record date column colored in critical when record type is error
- Fix `getSpecificValueToDisplay` dispatch for record date field

### 3.3.0 — Toner tracking for in-use cartridges and SNMP color mapping
- Add `snmp_color` field to `expected_yields` table linking cartridge types to SNMP toner colors
- Add dropdown on CartridgeItemType form for SNMP color selection
- Display in-use cartridge data: printer counter, consumed %, printed pages, estimated coverage, estimated remaining pages, estimated end date
- Hide native "Cartridge type" column from used cartridges table
- Date format respects GLPI configuration
- Add pt_BR translations for all new strings

### 3.2.0 — Expected yield field and cartridge summary table
- Add "Expected yield (ISO 5%)" field to CartridgeItemType form
- Add yield summary table grouped by cartridge type on Printer > Cartridges tab
- Summary shows: expected yield, total printed, average per cartridge, actual coverage %
- Group by `cartridgeitemtypes_id` with fallback to `cartridgeitems_id`

### 3.1.1 — Fix yield calculation to group by cartridge type instead of model
- Interchangeable cartridge models from different distributors now share yield history when they have the same `cartridgeitemtypes_id`
- Falls back to grouping by `cartridgeitems_id` when type is not set

### 3.1.0 — Fix printed pages yield calculation for multi-cartridge printers
- The "Printed pages" column in the Worn Cartridges tab was calculated sequentially across all cartridge types, producing incorrect yield values for printers with multiple simultaneous toner types (e.g. CMYK)
- Adds a `POST_SHOW_TAB` hook that recalculates yield per cartridge model (`cartridgeitems_id`), so each cartridge's printed pages reflects actual usage since the previous cartridge of the same type was replaced

### 3.0.0 — GLPI 11 compatibility
- Update plugin for GLPI 11.x
- Fix printer page count update after a successful counter collection (manual or via CRON)

---

## Original features

- Printer counter polling via SNMP
- Per-printer and per-entity configuration
- Cost management per entity
- Supports 3,000–4,500 printers

## Requirements

- GLPI 11.x (not compatible with 10.x and earlier)
- PHP 8.2+ with SNMP module
- SNMP network direct access to printers

## Installation

Clone this repository into your GLPI `plugins/` folder:

```bash
git clone https://github.com/brunoas/printercounters
```

Then enable the plugin in **GLPI > Setup > Plugins**.

## License

GPLv2+ — same as the original project.  
Original copyright: [Infotel](https://blogglpi.infotel.com)  
Fork and modifications: [Bruno Andrade](https://github.com/brunoas)

## Original documentation

https://github.com/InfotelGLPI/printercounters/wiki/Notice-d%27utilisation

---
---

# printercounters (fork) — pt_BR

Plugin de gerenciamento de contadores de impressoras via SNMP para o GLPI.

Fork do [printercounters original](https://github.com/InfotelGLPI/printercounters) desenvolvido pela [Infotel](https://blogglpi.infotel.com), atualizado para compatibilidade com o GLPI 11 e estendido com novos recursos de rastreamento de cartuchos e análise de rendimento por [Bruno Andrade](https://github.com/brunoas).

> O projeto original está inativo desde a versão 2.0.2 e não é compatível com o GLPI 11. Este fork retoma o desenvolvimento a partir do ponto em que ele parou.

---

## O que este fork adiciona

### 3.4.0 — Rótulos de cores SNMP traduzidos, melhorias nas colunas de busca e destaque de datas de erro
- Tradução dos nomes de cores SNMP no dropdown e nas colunas de busca (pt_BR)
- Correção na validação do `save()` após mudança de tipo do array `snmp_colors`
- Exibe traço para cartuchos sem consumo de toner em todas as colunas de busca
- Ordenação canônica consistente de cores (KCMY) nas colunas de toner, cobertura e data
- Impressoras de toner único exibem apenas o valor; multitoner exibem "valor (Cor)"
- Coluna de data do último registro destacada em vermelho crítico quando o tipo é erro
- Correção no dispatch de `getSpecificValueToDisplay` para o campo de data do registro

### 3.3.0 — Rastreamento de toner para cartuchos em uso e mapeamento de cores SNMP
- Adição do campo `snmp_color` na tabela `expected_yields`, vinculando tipos de cartucho às cores de toner SNMP
- Dropdown de seleção de cor SNMP no formulário de CartridgeItemType
- Exibição de dados dos cartuchos em uso: contador da impressora, % consumida, páginas impressas, cobertura estimada, páginas restantes estimadas e data de término estimada
- Ocultação da coluna nativa "Tipo de cartucho" na aba de cartuchos usados
- Formato de data respeita a configuração do GLPI
- Traduções pt_BR para todas as novas strings

### 3.2.0 — Campo de rendimento esperado e tabela resumo de cartuchos
- Adição do campo "Rendimento esperado (ISO 5%)" no formulário de CartridgeItemType
- Tabela resumo de rendimento agrupada por tipo de cartucho na aba Impressora > Cartuchos
- Resumo exibe: rendimento esperado, total impresso, média por cartucho e cobertura real (%)
- Agrupamento por `cartridgeitemtypes_id` com fallback para `cartridgeitems_id`

### 3.1.1 — Correção no cálculo de rendimento: agrupamento por tipo em vez de modelo
- Modelos de cartuchos intercambiáveis de distribuidores diferentes agora compartilham o histórico de rendimento quando possuem o mesmo `cartridgeitemtypes_id`
- Fallback para agrupamento por `cartridgeitems_id` quando o tipo não está definido

### 3.1.0 — Correção no cálculo de páginas impressas para impressoras com múltiplos cartuchos
- A coluna "Páginas impressas" na aba Cartuchos Gastos era calculada de forma sequencial entre todos os tipos de cartucho, gerando valores incorretos para impressoras com múltiplos toners simultâneos (ex.: CMYK)
- Adicionado hook `POST_SHOW_TAB` que recalcula o rendimento por modelo de cartucho (`cartridgeitems_id`), de forma que as páginas impressas de cada cartucho reflitam o uso real desde a última substituição do mesmo tipo

### 3.0.0 — Compatibilidade com GLPI 11
- Atualização do plugin para o GLPI 11
- Correção na atualização do contador de páginas da impressora após coleta bem-sucedida de contadores (manual ou via CRON)

---

## Funcionalidades originais

- Coleta de contadores via SNMP
- Configuração por impressora e por entidade
- Gestão de custos por entidade
- Suporte a 3.000–4.500 impressoras

## Requisitos

- GLPI 11.x (incompatível com versão 10.x e anteriores)
- PHP 8.2+ com módulo SNMP
- Acesso SNMP direto às impressoras

## Instalação

Clone este repositório na pasta `plugins/` do seu GLPI:

```bash
git clone https://github.com/brunoas/printercounters
```

Em seguida, ative o plugin em **GLPI > Configuração > Plugins**.

## Licença

GPLv2+ — mesmos termos do projeto original.  
Copyright original: [Infotel](https://blogglpi.infotel.com)  
Fork e modificações: [Bruno Andrade](https://github.com/brunoas)

## Documentação original

https://github.com/InfotelGLPI/printercounters/wiki/Notice-d%27utilisation