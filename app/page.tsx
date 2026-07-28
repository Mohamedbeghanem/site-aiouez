"use client";

import { FormEvent, useState } from "react";

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

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const name = String(form.get("name") || "");
    const company = String(form.get("company") || "");
    const email = String(form.get("email") || "");
    const need = String(form.get("need") || "");
    const subject = encodeURIComponent(`Demande de consultation — ${company || name}`);
    const body = encodeURIComponent(
      `Bonjour Cabinet Aiouez,\n\nJe souhaite échanger au sujet de : ${need}\n\nNom : ${name}\nEntreprise : ${company}\nEmail : ${email}\n\nCordialement,`,
    );
    window.location.href = `mailto:rahim@aouiz-dz.com?subject=${subject}&body=${body}`;
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

      <header className="header">
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
          className="menu-toggle"
          type="button"
          aria-label={menuOpen ? "Fermer le menu" : "Ouvrir le menu"}
          aria-expanded={menuOpen}
          onClick={() => setMenuOpen((open) => !open)}
        >
          <span />
          <span />
        </button>

        {menuOpen && (
          <nav className="mobile-menu" aria-label="Navigation mobile">
            <a href="#cabinet" onClick={closeMenu}>Le cabinet</a>
            <a href="#expertises" onClick={closeMenu}>Expertises</a>
            <a href="#approche" onClick={closeMenu}>Notre approche</a>
            <a href="#contact" onClick={closeMenu}>Demander un devis</a>
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

      <section className="about section" id="cabinet">
        <div className="about-label">
          <span className="section-number">01</span>
          <span>Le cabinet</span>
        </div>
        <div className="about-statement">
          <h2>
            La rigueur d’un auditeur.
            <span>La proximité d’un partenaire.</span>
          </h2>
          <div className="about-copy">
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
          </div>
        </div>
      </section>

      <section className="services" id="expertises">
        <div className="section services-inner">
          <div className="section-heading">
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

          <div className="service-list">
            {services.map((service) => (
              <article key={service.number}>
                <span className="service-number">{service.number}</span>
                <div className="service-title">
                  <h3>{service.title}</h3>
                  <span aria-hidden="true">↗</span>
                </div>
                <p>{service.text}</p>
                <ul>
                  {service.details.map((detail) => (
                    <li key={detail}>{detail}</li>
                  ))}
                </ul>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="approach section" id="approche">
        <div className="approach-card">
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

      <section className="audiences">
        <div className="section audiences-inner">
          <div>
            <span className="kicker">Des solutions à votre échelle</span>
            <h2>À chaque structure, une réponse adaptée.</h2>
          </div>
          <div className="audience-grid">
            {audiences.map((audience, index) => (
              <div key={audience}>
                <span>0{index + 1}</span>
                <strong>{audience}</strong>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="contact section" id="contact">
        <div className="contact-copy">
          <div className="about-label">
            <span className="section-number">04</span>
            <span>Contact</span>
          </div>
          <h2>Parlons de votre entreprise.</h2>
          <p>
            Décrivez-nous votre besoin. Votre demande sera préparée dans votre
            messagerie pour un échange direct et confidentiel avec le cabinet.
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

        <form className="contact-form" onSubmit={handleSubmit}>
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
          <button className="button button-solid" type="submit">
            Préparer ma demande <span aria-hidden="true">→</span>
          </button>
          <small className="privacy">
            Vos informations ne sont pas stockées sur ce site.
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
        </div>
      </footer>
    </main>
  );
}
