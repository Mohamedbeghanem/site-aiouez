"use client";

import { FormEvent, useEffect, useState } from "react";

const services = [
  {
    number: "01",
    title: "Commissariat aux comptes",
    text: "Certification des comptes annuels, contrôle légal et rapports spéciaux conduits avec indépendance et rigueur.",
    details: ["Audit légal", "Certification", "Rapports réglementaires"],
  },
  {
    number: "02",
    title: "Expertise comptable",
    text: "Une comptabilité fiable, structurée et toujours à jour pour vous permettre de piloter votre activité sereinement.",
    details: ["Tenue comptable", "États financiers", "Paie & déclarations"],
  },
  {
    number: "03",
    title: "Fiscalité",
    text: "Sécurisation de vos obligations fiscales et accompagnement lors des déclarations, contrôles et décisions structurantes.",
    details: ["Conseil fiscal", "Déclarations", "Assistance au contrôle"],
  },
  {
    number: "04",
    title: "Conseil en gestion",
    text: "Des indicateurs lisibles et des recommandations concrètes pour éclairer vos choix et soutenir votre croissance.",
    details: ["Tableaux de bord", "Diagnostic", "Aide à la décision"],
  },
];

const audiences = [
  "PME & entreprises familiales",
  "Startups & sociétés en croissance",
  "Groupes & grandes entreprises",
  "Professions libérales",
];

export default function Home() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [activeService, setActiveService] = useState(0);
  const [formState, setFormState] = useState<{
    status: "idle" | "submitting" | "success" | "error";
    message: string;
    reference?: string;
  }>({ status: "idle", message: "" });
  const selectedService = services[activeService];

  useEffect(() => {
    const reducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    ).matches;

    if (reducedMotion) return;

    const root = document.documentElement;
    const revealElements = Array.from(
      document.querySelectorAll<HTMLElement>("[data-reveal]"),
    );

    root.classList.add("motion-enabled");

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        });
      },
      {
        threshold: 0.14,
        rootMargin: "0px 0px -8% 0px",
      },
    );

    revealElements.forEach((element) => observer.observe(element));

    return () => {
      observer.disconnect();
      root.classList.remove("motion-enabled");
    };
  }, []);

  useEffect(() => {
    if (!menuOpen) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") setMenuOpen(false);
    }

    function handleResize() {
      if (window.innerWidth > 1080) setMenuOpen(false);
    }

    window.addEventListener("keydown", handleKeyDown);
    window.addEventListener("resize", handleResize);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", handleKeyDown);
      window.removeEventListener("resize", handleResize);
    };
  }, [menuOpen]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    setFormState({ status: "submitting", message: "Envoi en cours…" });

    try {
      const response = await fetch("/api/contact.php", {
        method: "POST",
        headers: { Accept: "application/json" },
        body: form,
      });
      const result = await response.json();
      if (!response.ok || !result.ok) {
        throw new Error(result.message || "La demande n’a pas pu être envoyée.");
      }
      formElement.reset();
      setFormState({
        status: "success",
        message: result.message,
        reference: result.reference,
      });
    } catch (error) {
      setFormState({
        status: "error",
        message:
          error instanceof Error
            ? error.message
            : "La demande n’a pas pu être envoyée.",
      });
    }
  }

  function closeMenu() {
    setMenuOpen(false);
  }

  return (
    <main>
      <div className="announcement">
        <span>Cabinet agréé à Alger</span>
        <a href="tel:+213541310255">+213 (0) 541 31 02 55</a>
      </div>

      <header className={`header ${menuOpen ? "menu-active" : ""}`}>
        <a className="logo" href="#accueil" aria-label="Aiouez — Accueil">
          <img src="/logo-aiouez.svg" alt="Aiouez — Commissaire aux comptes" />
        </a>

        <nav className="main-nav" aria-label="Navigation principale">
          <a href="#cabinet">Le cabinet</a>
          <a href="#expertises">Expertises</a>
          <a href="#approche">Notre approche</a>
          <a href="#contact">Contact</a>
        </nav>

        <a className="nav-cta" href="#contact">
          Demander un devis <span aria-hidden="true">↗</span>
        </a>

        <button
          className={`menu-toggle ${menuOpen ? "is-open" : ""}`}
          type="button"
          aria-label={menuOpen ? "Fermer le menu" : "Ouvrir le menu"}
          aria-expanded={menuOpen}
          aria-controls="mobile-navigation"
          onClick={() => setMenuOpen((open) => !open)}
        >
          <span />
          <span />
        </button>

        {menuOpen && (
          <nav
            className="mobile-menu"
            id="mobile-navigation"
            aria-label="Navigation mobile"
          >
            <div className="mobile-menu-eyebrow">
              <span>Navigation</span>
              <small>Cabinet Aiouez · Alger</small>
            </div>

            <div className="mobile-menu-links">
              <a href="#cabinet" onClick={closeMenu}>
                <span>01</span><strong>Le cabinet</strong><i aria-hidden="true">↗</i>
              </a>
              <a href="#expertises" onClick={closeMenu}>
                <span>02</span><strong>Expertises</strong><i aria-hidden="true">↗</i>
              </a>
              <a href="#approche" onClick={closeMenu}>
                <span>03</span><strong>Notre approche</strong><i aria-hidden="true">↗</i>
              </a>
              <a href="#contact" onClick={closeMenu}>
                <span>04</span><strong>Demander un devis</strong><i aria-hidden="true">↗</i>
              </a>
            </div>

            <div className="mobile-menu-footer">
              <div>
                <small>Commissaire aux comptes</small>
                <strong>Indépendance · Rigueur · Confiance</strong>
              </div>
              <a href="tel:+213541310255">
                Appeler le cabinet <span aria-hidden="true">→</span>
              </a>
            </div>
          </nav>
        )}
      </header>

      <section className="hero" id="accueil">
        <div className="hero-curve curve-blue" aria-hidden="true" />
        <div className="hero-curve curve-green" aria-hidden="true" />

        <div className="hero-copy">
          <span className="kicker">Commissaire aux comptes · Comptable agréé</span>
          <h1>
            Vos comptes méritent
            <em>un regard de confiance.</em>
          </h1>
          <p>
            Le Cabinet Aiouez accompagne les entreprises algériennes dans la
            certification de leurs comptes, leur conformité et leurs décisions
            financières.
          </p>
          <div className="hero-actions">
            <a className="button button-solid" href="#contact">
              Échanger avec le cabinet <span aria-hidden="true">→</span>
            </a>
            <a className="button button-link" href="#expertises">
              Découvrir nos expertises
            </a>
          </div>
          <div className="hero-proof">
            <div>
              <strong>15+</strong>
              <span>années d’expérience</span>
            </div>
            <div>
              <strong>200+</strong>
              <span>missions d’audit</span>
            </div>
            <div>
              <strong>48h</strong>
              <span>pour un premier retour</span>
            </div>
          </div>
        </div>

        <div className="hero-media">
          <div className="photo-frame">
            <img
              src="https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=1400&q=85"
              alt="Professionnel examinant des états financiers et documents comptables"
            />
          </div>
          <div className="image-caption">
            <span className="caption-mark">A</span>
            <div>
              <strong>Audit & certification</strong>
              <small>Indépendance · Sincérité · Image fidèle</small>
            </div>
          </div>
          <div className="experience-badge">
            <span>Depuis</span>
            <strong>+15 ans</strong>
            <small>à vos côtés</small>
          </div>
          <div className="audit-note">
            <span className="audit-note-icon">✓</span>
            <div>
              <small>Opinion & certification</small>
              <strong>Une information financière fiable</strong>
            </div>
          </div>
        </div>
      </section>

      <section className="signature-strip" aria-label="Valeurs du cabinet">
        <span>Indépendance</span>
        <i>•</i>
        <span>Rigueur</span>
        <i>•</i>
        <span>Confidentialité</span>
        <i>•</i>
        <span>Proximité</span>
      </section>

      <div
        className="editorial-ribbon"
        aria-label="Audit, Expertise, Conseil"
      >
        <div className="ribbon-track" aria-hidden="true">
          {[0, 1].map((copy) => (
            <div className="ribbon-group" key={copy}>
              <span>Audit</span>
              <i>◆</i>
              <span>Expertise</span>
              <i>◆</i>
              <span>Conseil</span>
              <i>◆</i>
            </div>
          ))}
        </div>
      </div>

      <section className="about section" id="cabinet">
        <div className="about-label" data-reveal="left">
          <span className="section-number">01</span>
          <span>Le cabinet</span>
        </div>
        <div className="about-statement">
          <h2 data-reveal="up">
            La rigueur d’un auditeur.
            <span>La proximité d’un partenaire.</span>
          </h2>
          <div className="about-layout">
            <div className="about-visual" data-reveal="scale">
              <img
                src="https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=1200&q=85"
                alt="Documents financiers examinés par un professionnel"
              />
              <div className="visual-quote">
                <span>“</span>
                <p>Rendre les chiffres lisibles pour rendre les décisions plus sûres.</p>
              </div>
            </div>
            <div className="about-copy" data-reveal="right">
              <p>
                Basé à Alger, le Cabinet Aiouez intervient auprès des dirigeants,
                entrepreneurs et organisations qui recherchent un accompagnement
                fiable, indépendant et réellement adapté à leur activité.
              </p>
              <p>
                Notre rôle ne s’arrête pas aux chiffres : nous les rendons
                compréhensibles pour sécuriser vos obligations et donner plus de
                clarté à vos décisions.
              </p>
              <div className="about-signature">
                <strong>Cabinet Aiouez</strong>
                <span>Commissaire aux comptes · Alger</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="services" id="expertises">
        <div className="section services-inner">
          <div className="section-heading" data-reveal="left">
            <div className="about-label">
              <span className="section-number">02</span>
              <span>Nos expertises</span>
            </div>
            <h2>Un accompagnement complet, à chaque étape.</h2>
            <p>
              Quatre domaines complémentaires pour garantir la fiabilité de vos
              comptes et accompagner durablement votre entreprise.
            </p>
          </div>

          <div className="expertise-studio" data-reveal="right">
            <div className="service-tabs" role="tablist" aria-label="Domaines d’expertise">
              {services.map((service, index) => (
                <button
                  key={service.number}
                  type="button"
                  role="tab"
                  aria-selected={activeService === index}
                  className={activeService === index ? "active" : ""}
                  onClick={() => setActiveService(index)}
                >
                  <span>{service.number}</span>
                  <strong>{service.title}</strong>
                  <i aria-hidden="true">→</i>
                </button>
              ))}
            </div>

            <article className="service-feature" role="tabpanel">
              <div className="feature-orbit" aria-hidden="true" />
              <div className="feature-top">
                <span>Expertise / {selectedService.number}</span>
                <span>Cabinet Aiouez</span>
              </div>
              <div className="feature-main">
                <span className="feature-index">{selectedService.number}</span>
                <h3>{selectedService.title}</h3>
                <p>{selectedService.text}</p>
              </div>
              <ul>
                {selectedService.details.map((detail) => (
                  <li key={detail}><span>✓</span>{detail}</li>
                ))}
              </ul>
              <a href="#contact">Parler à un expert <span aria-hidden="true">↗</span></a>
            </article>
          </div>
        </div>
      </section>

      <section className="approach section" id="approche">
        <div className="approach-card" data-reveal="scale">
          <div className="approach-copy">
            <div className="about-label light-label">
              <span className="section-number">03</span>
              <span>Notre approche</span>
            </div>
            <h2>Clair dans les échanges. Précis dans l’analyse.</h2>
            <p>
              Chaque mission suit une méthode simple, transparente et tournée
              vers des résultats utiles à votre entreprise.
            </p>
          </div>
          <ol className="steps">
            <li>
              <span>01</span>
              <div>
                <strong>Comprendre</strong>
                <p>Votre activité, vos enjeux et votre organisation.</p>
              </div>
            </li>
            <li>
              <span>02</span>
              <div>
                <strong>Contrôler</strong>
                <p>Les comptes, les risques et les points de conformité.</p>
              </div>
            </li>
            <li>
              <span>03</span>
              <div>
                <strong>Restituer</strong>
                <p>Une conclusion claire et des recommandations concrètes.</p>
              </div>
            </li>
          </ol>
        </div>
      </section>

      <section className="assurance">
        <div className="section assurance-inner">
          <div className="assurance-photo" data-reveal="left">
            <img
              src="https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1400&q=85"
              alt="Réunion professionnelle autour de documents de gestion"
            />
            <div className="assurance-stamp">
              <span>100%</span>
              <small>confidentiel</small>
            </div>
          </div>
          <div className="assurance-copy" data-reveal="right">
            <span className="kicker">Une relation durable</span>
            <h2>Votre entreprise n’est pas un dossier parmi d’autres.</h2>
            <p>
              Un interlocuteur attentif, des échanges directs et une lecture
              précise de vos enjeux. Notre accompagnement s’adapte à votre
              organisation, à votre secteur et à votre rythme.
            </p>
            <div className="assurance-points">
              <div><strong>Indépendance</strong><span>Un regard objectif sur vos comptes</span></div>
              <div><strong>Disponibilité</strong><span>Des réponses claires, sans détour</span></div>
              <div><strong>Continuité</strong><span>Une connaissance durable de votre activité</span></div>
            </div>
          </div>
        </div>
      </section>

      <section className="audiences">
        <div className="section audiences-inner">
          <div data-reveal="left">
            <span className="kicker">Des solutions à votre échelle</span>
            <h2>À chaque structure, une réponse adaptée.</h2>
          </div>
          <div className="audience-grid">
            {audiences.map((audience, index) => (
              <div data-reveal="up" key={audience}>
                <span>0{index + 1}</span>
                <strong>{audience}</strong>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="insights section">
        <div className="insights-heading" data-reveal="up">
          <div className="about-label">
            <span className="section-number">05</span>
            <span>Repères & conseils</span>
          </div>
          <h2>Comprendre aujourd’hui. Anticiper demain.</h2>
        </div>
        <div className="insight-grid">
          <article data-reveal="up">
            <span>Audit légal</span>
            <h3>Bien préparer une mission de commissariat aux comptes</h3>
            <p>Les documents, interlocuteurs et étapes à anticiper pour une mission fluide.</p>
            <a href="#contact">En discuter avec le cabinet ↗</a>
          </article>
          <article data-reveal="up">
            <span>Clôture comptable</span>
            <h3>Transformer la clôture en outil de pilotage</h3>
            <p>Au-delà de l’obligation, faire des états financiers un véritable support de décision.</p>
            <a href="#contact">En discuter avec le cabinet ↗</a>
          </article>
          <article data-reveal="up">
            <span>Fiscalité</span>
            <h3>Sécuriser vos déclarations et vos échéances</h3>
            <p>Une organisation rigoureuse pour limiter les risques et conserver une vision claire.</p>
            <a href="#contact">En discuter avec le cabinet ↗</a>
          </article>
        </div>
      </section>

      <section className="contact section" id="contact">
        <div className="contact-copy" data-reveal="left">
          <div className="about-label">
            <span className="section-number">06</span>
            <span>Contact</span>
          </div>
          <h2>Parlons de votre entreprise.</h2>
          <p>
            Décrivez-nous votre besoin. Votre demande sera transmise directement
            au cabinet pour un échange confidentiel.
          </p>
          <div className="contact-info">
            <a href="tel:+213541310255">
              <small>Téléphone</small>
              <strong>+213 (0) 541 31 02 55</strong>
            </a>
            <a href="mailto:rahim@aouiz-dz.com">
              <small>Email</small>
              <strong>rahim@aouiz-dz.com</strong>
            </a>
            <div>
              <small>Adresse</small>
              <strong>Djasr Kasentina, Alger</strong>
            </div>
          </div>
        </div>

        <form className="contact-form" data-reveal="right" onSubmit={handleSubmit}>
          <label className="honeypot" aria-hidden="true">
            <span>Site web</span>
            <input name="website" type="text" tabIndex={-1} autoComplete="off" />
          </label>
          <div className="form-row">
            <label>
              <span>Nom et prénom *</span>
              <input name="name" type="text" placeholder="Votre nom" required />
            </label>
            <label>
              <span>Entreprise</span>
              <input name="company" type="text" placeholder="Votre société" />
            </label>
          </div>
          <label>
            <span>Email professionnel *</span>
            <input name="email" type="email" placeholder="vous@entreprise.dz" required />
          </label>
          <label>
            <span>Téléphone</span>
            <input name="phone" type="tel" placeholder="+213…" autoComplete="tel" />
          </label>
          <label>
            <span>Votre besoin *</span>
            <select name="need" defaultValue="" required>
              <option value="" disabled>Sélectionner une expertise</option>
              <option>Commissariat aux comptes</option>
              <option>Expertise comptable</option>
              <option>Fiscalité</option>
              <option>Conseil en gestion</option>
              <option>Autre demande</option>
            </select>
          </label>
          <label>
            <span>Précisions</span>
            <textarea
              name="message"
              rows={4}
              placeholder="Présentez brièvement votre situation ou votre échéance."
            />
          </label>
          <button
            className="button button-solid"
            type="submit"
            disabled={formState.status === "submitting"}
          >
            {formState.status === "submitting" ? "Envoi en cours…" : "Envoyer ma demande"}
            <span aria-hidden="true">→</span>
          </button>
          {formState.status !== "idle" && (
            <div
              className={`form-feedback form-feedback-${formState.status}`}
              role={formState.status === "error" ? "alert" : "status"}
              aria-live="polite"
            >
              <strong>
                {formState.status === "success" ? "Demande reçue" : formState.status === "error" ? "Envoi impossible" : "Transmission"}
              </strong>
              <span>{formState.message}</span>
              {formState.reference && <small>Référence : {formState.reference}</small>}
              {formState.status === "error" && (
                <a href="mailto:rahim@aouiz-dz.com">Envoyer plutôt un email</a>
              )}
            </div>
          )}
          <small className="privacy">
            Vos informations sont utilisées uniquement pour traiter votre demande.
          </small>
        </form>
      </section>

      <footer className="footer">
        <div className="footer-main">
          <a className="footer-logo" href="#accueil" aria-label="Aiouez — Retour en haut">
            <img src="/logo-aiouez.svg" alt="Aiouez — Commissaire aux comptes" />
          </a>
          <p>
            Commissariat aux comptes · Expertise comptable
            <br />
            Fiscalité · Conseil en gestion
          </p>
          <a className="back-top" href="#accueil" aria-label="Retour en haut">↑</a>
        </div>
        <div className="footer-bottom">
          <span>© 2026 Cabinet Aiouez. Tous droits réservés.</span>
          <span>Alger · Algérie</span>
          <a
            className="evotechly-credit"
            href="https://evotechly.com/"
            target="_blank"
            rel="noreferrer"
            aria-label="Site créé par Evotechly — ouvrir le site"
          >
            <span>Made by</span>
            <strong>Evotechly</strong>
            <i aria-hidden="true">↗</i>
          </a>
        </div>
      </footer>

      <a
        className={`whatsapp-button ${menuOpen ? "is-hidden" : ""}`}
        href="https://wa.me/213541310255"
        target="_blank"
        rel="noreferrer"
        title="WhatsApp"
        aria-label="Contacter le Cabinet Aiouez sur WhatsApp"
      >
        <span aria-hidden="true" />
      </a>
    </main>
  );
}
