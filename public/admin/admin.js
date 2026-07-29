const body = document.body;
const menuButton = document.querySelector(".menu-button");
const sidebar = document.querySelector(".sidebar");
const sidebarClosers = document.querySelectorAll("[data-sidebar-close]");

const setSidebar = (open) => {
  body.classList.toggle("sidebar-open", open);
  menuButton?.setAttribute("aria-expanded", String(open));
  if (open) {
    sidebar?.querySelector("a, button")?.focus();
  } else {
    menuButton?.focus();
  }
};

menuButton?.addEventListener("click", () => setSidebar(!body.classList.contains("sidebar-open")));
sidebarClosers.forEach((button) => button.addEventListener("click", () => setSidebar(false)));

const panel = document.querySelector(".detail-panel");
const closePanel = () => {
  const url = new URL(window.location.href);
  url.searchParams.delete("id");
  url.searchParams.delete("new");
  url.searchParams.delete("edit");
  window.location.href = url.toString();
};

document.querySelector("[data-close-panel]")?.addEventListener("click", closePanel);

if (panel) {
  panel.setAttribute("role", "dialog");
  panel.setAttribute("aria-modal", "true");
  panel.setAttribute("tabindex", "-1");
  requestAnimationFrame(() => {
    (panel.querySelector("input:not([type='hidden']), select, textarea, button, a") || panel).focus();
  });
}

const getFocusable = (container) => [...container.querySelectorAll(
  "a[href], button:not([disabled]), input:not([disabled]):not([type='hidden']), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex='-1'])"
)].filter((element) => !element.hidden && element.getClientRects().length);

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    if (panel) {
      closePanel();
      return;
    }
    if (body.classList.contains("sidebar-open")) {
      setSidebar(false);
      return;
    }
    document.querySelectorAll(".topbar-menu[open]").forEach((menu) => menu.removeAttribute("open"));
  }

  if (event.key === "Tab" && panel) {
    const focusable = getFocusable(panel);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }
});

document.addEventListener("click", (event) => {
  document.querySelectorAll(".topbar-menu[open]").forEach((menu) => {
    if (!menu.contains(event.target)) menu.removeAttribute("open");
  });
});

document.querySelectorAll("input[type='file']").forEach((input) => {
  input.addEventListener("change", () => {
    const label = input.closest("label");
    const output = label?.querySelector("[data-file-name]");
    if (output) output.textContent = input.files?.[0]?.name || "Aucun fichier sélectionné";
  });
});

const confirmDialog = document.querySelector("#confirm-dialog");
let pendingConfirmation = null;
document.querySelectorAll("form[data-confirm]").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (form.dataset.confirmed === "true") return;
    event.preventDefault();
    pendingConfirmation = form;
    const copy = confirmDialog?.querySelector("#confirm-copy");
    if (copy) copy.textContent = form.dataset.confirm || "Confirmer cette action ?";
    confirmDialog?.showModal();
  });
});
confirmDialog?.addEventListener("close", () => {
  if (confirmDialog.returnValue === "confirm" && pendingConfirmation) {
    pendingConfirmation.dataset.confirmed = "true";
    pendingConfirmation.requestSubmit();
  }
  pendingConfirmation = null;
});

document.querySelectorAll("form").forEach((form) => {
  form.addEventListener("invalid", () => form.classList.add("was-validated"), true);
  form.addEventListener("submit", () => {
    if (!form.checkValidity()) return;
    const submitter = form.querySelector("button[type='submit'], button:not([type])");
    if (!submitter) return;
    submitter.classList.add("is-loading");
    submitter.setAttribute("aria-busy", "true");
  });
});
