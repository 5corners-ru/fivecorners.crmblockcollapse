<?php
$MESS["FCO_CBC_HELP_PAGE_TITLE"] = "CRM Section Collapse — About module";
$MESS["FCO_CBC_HELP_LEAD"]       = "The module adds collapse/expand buttons to the sections of CRM entity cards (Deals, Leads, Contacts, Companies, Smart Processes). The state is remembered per user.";

$MESS["FCO_CBC_HELP_WHAT_TITLE"] = "What the module does";
$MESS["FCO_CBC_HELP_WHAT_TEXT"]  = "On a CRM card every field section gets a toggle button and a clickable header: a click collapses or expands the section content with an animation.
The user's choice is saved in the database and re-applied the next time the card is opened — on any device.";

$MESS["FCO_CBC_HELP_USER_TITLE"] = "For the user";
$MESS["FCO_CBC_HELP_USER_TEXT"]  = "Open a deal, lead, contact, company or smart-process card — collapse buttons appear next to the section headers.
Collapse the sections you do not need — next time everything is restored the way you left it.
When the stage changes, sections from the stage rules expand automatically.";

$MESS["FCO_CBC_HELP_ADMIN_TITLE"] = "For the administrator";
$MESS["FCO_CBC_HELP_ADMIN_TEXT"]  = "The «Settings» tab — enable the entity types the module works on and, if needed, pick specific smart processes.
The «Stage rules» tab — define sections that must be forcibly expanded on certain stages.";

$MESS["FCO_CBC_HELP_RULES_TITLE"] = "How stage rules work";
$MESS["FCO_CBC_HELP_RULES_TEXT"]  = "For each stage you can specify a list of sections that will always be expanded while the entity is on that stage.
A stage rule takes priority over the user's personal choice: if a section is listed in the rule, it is expanded even if the user collapsed it.
Section names are entered exactly as shown in the card (case-insensitive), one per line.";

$MESS["FCO_CBC_HELP_TIPS_TITLE"] = "Tips";
$MESS["FCO_CBC_HELP_TIPS_TEXT"]  = "When there are many funnels and stages — use the search and the «only with rules» filter on the «Stage rules» tab.
The badge next to a funnel shows in how many of its stages rules are already set.
If the buttons do not appear — make sure the entity type is enabled on the «Settings» tab and refresh the card page.";
