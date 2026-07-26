// assets/js/nav-active.js
document.addEventListener("DOMContentLoaded", () => {
  const navLinks = document.querySelectorAll(".navigation .nav");
  const currentPage = location.pathname.split("/").pop(); 

  navLinks.forEach(link => {
    link.classList.remove("active");

    const linkPage = link.getAttribute("href").split("/").pop();
    if (linkPage === currentPage) {
      link.classList.add("active");
    }
  });
});
