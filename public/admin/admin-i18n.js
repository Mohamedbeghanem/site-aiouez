(() => {
  const config = window.AIOUEZ_I18N;
  if (!config || config.locale === "fr") return;

  const catalog = config.catalog || {};
  const translate = (value) => {
    if (!value) return value;
    const exact = catalog[value];
    if (exact) return exact;

    const patterns = config.locale === "ar" ? [
      [/^(\d+) lead\(s\)$/u, "$1 عميل محتمل"],
      [/^(\d+) contact\(s\)$/u, "$1 جهة اتصال"],
      [/^(\d+) entreprise\(s\) suivie\(s\)$/u, "$1 شركة"],
      [/^(\d+) opportunité\(s\)$/u, "$1 فرصة"],
      [/^(\d+) tâche\(s\)$/u, "$1 مهمة"],
      [/^(\d+) activité\(s\)$/u, "$1 نشاط"],
      [/^(\d+) document\(s\)$/u, "$1 مستند"],
      [/^(\d+) élément\(s\)$/u, "$1 عنصر"],
      [/^(\d+) non lue\(s\)$/u, "$1 غير مقروء"],
      [/^(\d+) notification\(s\) non lue\(s\)$/u, "$1 إشعار غير مقروء"],
      [/^(\d+) étape\(s\) terminée\(s\) sur (\d+)$/u, "$1 خطوة مكتملة من $2"],
      [/^(\d+) contact\(s\) · (\d+) opportunité\(s\)$/u, "$1 جهة اتصال · $2 فرصة"],
      [/^(\d+) sur (\d+) leads$/u, "$1 من $2 عميل محتمل"],
      [/^(\d+) gagnée\(s\)$/u, "$1 مربوحة"],
      [/^(\d+) perdue\(s\)$/u, "$1 مفقودة"],
      [/^(\d+) lead\(s\) importé\(s\)\.$/u, "تم استيراد $1 عميل محتمل."],
      [/^Rechercher dans (.+)…$/u, "البحث في $1…"],
      [/^Terminer : (.+)$/u, "إكمال: $1"],
      [/^Rouvrir : (.+)$/u, "إعادة فتح: $1"],
    ] : [
      [/^(\d+) lead\(s\)$/u, "$1 lead(s)"],
      [/^(\d+) contact\(s\)$/u, "$1 contact(s)"],
      [/^(\d+) entreprise\(s\) suivie\(s\)$/u, "$1 company/companies"],
      [/^(\d+) opportunité\(s\)$/u, "$1 opportunity/opportunities"],
      [/^(\d+) tâche\(s\)$/u, "$1 task(s)"],
      [/^(\d+) activité\(s\)$/u, "$1 activity/activities"],
      [/^(\d+) document\(s\)$/u, "$1 document(s)"],
      [/^(\d+) élément\(s\)$/u, "$1 item(s)"],
      [/^(\d+) non lue\(s\)$/u, "$1 unread"],
      [/^(\d+) notification\(s\) non lue\(s\)$/u, "$1 unread notification(s)"],
      [/^(\d+) étape\(s\) terminée\(s\) sur (\d+)$/u, "$1 of $2 steps completed"],
      [/^(\d+) contact\(s\) · (\d+) opportunité\(s\)$/u, "$1 contact(s) · $2 opportunity/opportunities"],
      [/^(\d+) sur (\d+) leads$/u, "$1 of $2 leads"],
      [/^(\d+) gagnée\(s\)$/u, "$1 won"],
      [/^(\d+) perdue\(s\)$/u, "$1 lost"],
      [/^(\d+) lead\(s\) importé\(s\)\.$/u, "$1 lead(s) imported."],
      [/^Rechercher dans (.+)…$/u, "Search $1…"],
      [/^Terminer : (.+)$/u, "Complete: $1"],
      [/^Rouvrir : (.+)$/u, "Reopen: $1"],
    ];
    for (const [pattern, replacement] of patterns) {
      if (pattern.test(value)) return value.replace(pattern, replacement);
    }
    const probability = value.match(/^(\d+)% de probabilité$/u);
    if (probability) {
      return config.locale === "ar" ? `احتمال ${probability[1]}%` : `${probability[1]}% probability`;
    }
    const prefixedMeta = value.match(/^(Appel|Réunion|Email|Tâche|Document|Système|Administratif|Identite|Financier|Comptabilite|Juridique|Fiscal|Mission|Organisation) · (.+)$/u);
    if (prefixedMeta && catalog[prefixedMeta[1]]) {
      return `${catalog[prefixedMeta[1]]} · ${prefixedMeta[2]}`;
    }
    return value;
  };
  window.aiouezTranslate = translate;

  const skipParents = new Set(["SCRIPT", "STYLE", "TEXTAREA", "OPTION"]);
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const nodes = [];
  while (walker.nextNode()) nodes.push(walker.currentNode);
  for (const node of nodes) {
    if (!node.parentElement || skipParents.has(node.parentElement.tagName)) continue;
    if (node.parentElement.closest("[data-no-translate]")) continue;
    const original = node.nodeValue || "";
    const trimmed = original.trim();
    if (!trimmed) continue;
    const translated = translate(trimmed);
    if (translated !== trimmed) {
      node.nodeValue = original.replace(trimmed, translated);
    }
  }

  for (const element of document.querySelectorAll("[placeholder], [aria-label], [title]")) {
    for (const attribute of ["placeholder", "aria-label", "title"]) {
      if (!element.hasAttribute(attribute)) continue;
      const original = element.getAttribute(attribute);
      const translated = translate(original);
      if (translated !== original) element.setAttribute(attribute, translated);
    }
  }

  for (const option of document.querySelectorAll("option")) {
    const original = option.textContent.trim();
    const translated = translate(original);
    if (translated !== original) option.textContent = translated;
  }

  document.dispatchEvent(new CustomEvent("aiouez:localized", { detail: config }));
})();
