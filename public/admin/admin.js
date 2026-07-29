const menuButton = document.querySelector(".menu-button");
const sidebar = document.querySelector(".sidebar");

menuButton?.addEventListener("click", () => {
  const open = sidebar?.classList.toggle("open") ?? false;
  menuButton.setAttribute("aria-expanded", String(open));
});

document.querySelector("[data-close-detail]")?.addEventListener("click", () => {
  window.location.href = "/admin/";
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && document.querySelector(".detail-panel")) {
    window.location.href = "/admin/";
  }
});
