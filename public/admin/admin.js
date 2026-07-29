const menuButton = document.querySelector(".menu-button");
const sidebar = document.querySelector(".sidebar");

menuButton?.addEventListener("click", () => {
  const open = sidebar?.classList.toggle("open") ?? false;
  menuButton.setAttribute("aria-expanded", String(open));
});

const closePanel = () => {
  const url = new URL(window.location.href);
  url.searchParams.delete("id");
  url.searchParams.delete("new");
  url.searchParams.delete("edit");
  window.location.href = url.toString();
};

document.querySelector("[data-close-panel]")?.addEventListener("click", closePanel);

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && document.querySelector(".detail-panel")) {
    closePanel();
  }
});

document.addEventListener("click", (event) => {
  if (sidebar?.classList.contains("open") && !sidebar.contains(event.target) && !menuButton?.contains(event.target)) {
    sidebar.classList.remove("open");
    menuButton?.setAttribute("aria-expanded", "false");
  }
});
