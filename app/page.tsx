"use client";

import { FormEvent, useState } from "react";

const services = [
  {
    index: "01",
    eyebrow: "Confiance",
    title: "Audit légal",
    description:
      "Certification des comptes, contrôle des procédures et rapports réglementaires menés avec indépendance.",
    items: ["Commissariat aux comptes", "Audit contractuel", "Rapports spéciaux"],
    tone: "cyan",
  },
  {
    index: "02",
    eyebrow: "Maîtrise",
    title: "Expertise comptable",
    description:
      "Une information financière claire, fiable et exploitable pour piloter sereinement votre activité.",
    items: ["Tenue & révision", "États financiers", "Paie & déclarations"],
    tone: "mint",
  },
  {
    index: "03",
    eyebrow: "Conformité",
    title: "Conseil fiscal",
    description:
      "Sécurisation de vos obligations et optimisation responsable dans le respect du cadre algérien.",
    items: ["Planification fiscale", "Contrôles fiscaux", "Veille réglementaire"],
    tone: "blue",
  },
  {
    index: "04",
    eyebrow: "Performance",
    title: "Conseil en gestion",
    description:
      "Des indicateurs utiles et un regard extérieur pour éclairer chaque décision structurante.",
    items: ["Tableaux de bord", "Diagnostic financier", "Aide à la décision"],
    tone: "violet",
  },
];

const sectors = [
  "PME & entreprises familiales",
  "Startups & entreprises en croissance",
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
      <div className="site-shell">
        <header className="topbar">
          <a className="brand" href="#accueil" aria-label="Aiouez — Accueil">
            <span className="brand-mark" aria-hidden="true">
              A
            </span>
            <span className="brand-copy">
              <strong>Aiouez</strong>
              <small>Commissaire aux comptes</small>
            </span>
          </a>

          <nav className="desktop-nav" aria-label="Navigation principale">
            <a href="#expertises">Expertises</a>
            <a href="#methode">Méthode</a>
            <a href="#cabinet">Le cabinet</a>
          </nav>

          <a className="header-cta" href="#contact">
            Parlons de votre projet <span aria-hidden="true">↗</span>
          </a>

          <button
            className="menu-button"
            type="button"
            aria-label={menuOpen ? "Fermer le menu" : "Ouvrir le menu"}
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen((open) => !open)}
          >
            <span />
            <span />
          </button>

          {menuOpen && (
            <nav className="mobile-nav" aria-label="Navigation mobile">
              <a href="#expertises" onClick={closeMenu}>
                Expertises
              </a>
              <a href="#methode" onClick={closeMenu}>
                Méthode
              </a>
              <a href="#cabinet" onClick={closeMenu}>
                Le cabinet
              </a>
              <a href="#contact" onClick={closeMenu}>
                Demander une consultation
              </a>
            </nav>
          )}
        </header>

        <section className="hero" id="accueil">
          <div className="hero-grid" aria-hidden="true" />
          <div className="hero-glow hero-glow-one" aria-hidden="true" />
          <div className="hero-glow hero-glow-two" aria-hidden="true" />

          <div className="hero-copy">
            <div className="status-pill">
              <span />
              Cabinet agréé · Alger
            </div>
            <h1>
              La clarté financière,
              <br />
              <em>au service de vos décisions.</em>
            </h1>
            <p>
              Audit, expertise comptable et conseil pour les entreprises qui
              veulent avancer avec des chiffres fiables et une vision nette.
            </p>
            <div className="hero-actions">
              <a className="button button-primary" href="#contact">
                Demander un devis <span aria-hidden="true">→</span>
              </a>
              <a className="text-link" href="#expertises">
                Explorer nos expertises <span aria-hidden="true">↓</span>
              </a>
            </div>
            <div className="hero-metrics" aria-label="Chiffres clés du cabinet">
              <div>
                <strong>15+</strong>
                <span>ans d’expérience</span>
              </div>
              <div>
                <strong>200+</strong>
                <span>missions d’audit</span>
              </div>
              <div>
                <strong>48h</strong>
                <span>pour une première réponse</span>
              </div>
            </div>
          </div>

          <div className="signal-card" aria-label="Aperçu des domaines d’expertise">
            <div className="signal-topline">
              <span>Aiouez / Signal financier</span>
              <span className="live-dot">En direct</span>
            </div>
            <div className="signal-stage">
              <div className="orbit orbit-one" />
              <div className="orbit orbit-two" />
              <div className="orbit orbit-three" />
              <div className="signal-core">
                <span>A.</span>
                <small>Rigueur</small>
              </div>
              <div className="signal-tag tag-one">
                <span>01</span> Audit légal
              </div>
              <div className="signal-tag tag-two">
                <span>02</span> Conformité SCF
              </div>
              <div className="signal-tag tag-three">
                <span>03</span> Pilotage
              </div>
            </div>
            <div className="signal-footer">
              <div>
                <span>Indice de confiance</span>
                <strong>Élevé</strong>
              </div>
              <div className="micro-bars" aria-hidden="true">
                <i />
                <i />
                <i />
                <i />
                <i />
                <i />
                <i />
                <i />
              </div>
            </div>
          </div>
        </section>

        <div className="trust-strip" aria-label="Principes du cabinet">
          <span>Indépendance</span>
          <i>✦</i>
          <span>Confidentialité</span>
          <i>✦</i>
          <span>Conformité</span>
          <i>✦</i>
          <span>Vision stratégique</span>
        </div>
      </div>

      <section className="section services-section" id="expertises">
        <div className="section-heading">
          <div>
            <span className="eyebrow">Nos expertises</span>
            <h2>Vos chiffres parlent.<br />Nous les rendons décisifs.</h2>
          </div>
          <p>
            Un accompagnement complet, du respect de vos obligations à la
            transformation de l’information financière en décisions utiles.
          </p>
        </div>

        <div className="services-grid">
          {services.map((service) => (
            <article className={`service-card ${service.tone}`} key={service.title}>
              <div className="service-meta">
                <span>{service.index}</span>
                <small>{service.eyebrow}</small>
              </div>
              <div>
                <h3>{service.title}</h3>
                <p>{service.description}</p>
              </div>
              <ul>
                {service.items.map((item) => (
                  <li key={item}>
                    <span aria-hidden="true">↗</span>
                    {item}
                  </li>
                ))}
              </ul>
            </article>
          ))}
        </div>
      </section>

      <section className="method-section" id="methode">
        <div className="method-visual" aria-hidden="true">
          <div className="ledger-window">
            <div className="ledger-header">
              <span>Mission / 2026</span>
              <span>•••</span>
            </div>
            <div className="ledger-score">
              <small>Niveau de maîtrise</small>
              <strong>94<span>/100</span></strong>
            </div>
            <div className="ledger-chart">
              {[46, 62, 54, 78, 68, 88, 96].map((height, index) => (
                <i key={index} style={{ height: `${height}%` }} />
              ))}
            </div>
            <div className="ledger-legend">
              <span><i /> Risques maîtrisés</span>
              <span>Dernière analyse · aujourd’hui</span>
            </div>
          </div>
          <div className="audit-chip">
            <span>✓</span>
            <div>
              <strong>Dossier sécurisé</strong>
              <small>Points de contrôle validés</small>
            </div>
          </div>
        </div>

        <div className="method-copy">
          <span className="eyebrow">Notre méthode</span>
          <h2>Précis dans l’analyse.<br />Clair dans le conseil.</h2>
          <p>
            Nous combinons rigueur réglementaire, compréhension de votre
            activité et échanges directs. Vous savez toujours ce qui est fait,
            pourquoi, et ce qui vient ensuite.
          </p>
          <ol>
            <li>
              <span>01</span>
              <div>
                <strong>Comprendre</strong>
                <p>Vos enjeux, vos flux et vos priorités.</p>
              </div>
            </li>
            <li>
              <span>02</span>
              <div>
                <strong>Sécuriser</strong>
                <p>Les risques, obligations et points sensibles.</p>
              </div>
            </li>
            <li>
              <span>03</span>
              <div>
                <strong>Éclairer</strong>
                <p>Les décisions avec des recommandations concrètes.</p>
              </div>
            </li>
          </ol>
        </div>
      </section>

      <section className="section cabinet-section" id="cabinet">
        <div className="cabinet-intro">
          <span className="eyebrow">Cabinet Aiouez</span>
          <h2>Un partenaire indépendant.<br />Une vision engagée.</h2>
        </div>
        <div className="cabinet-story">
          <p className="story-lead">
            Plus qu’un contrôle, nous apportons un regard qui sécurise
            aujourd’hui et prépare demain.
          </p>
          <p>
            Basé à Alger, le Cabinet Aiouez accompagne les dirigeants avec une
            approche personnalisée, confidentielle et exigeante. Chaque mission
            est conduite au plus près du terrain et de la réalité de votre
            entreprise.
          </p>
          <div className="principle-row">
            <span>Proximité</span>
            <span>Réactivité</span>
            <span>Indépendance</span>
          </div>
        </div>
      </section>

      <section className="sectors-section">
        <div className="section sectors-inner">
          <div>
            <span className="eyebrow light">Pour qui ?</span>
            <h2>Une expertise qui s’adapte à votre échelle.</h2>
          </div>
          <div className="sector-list">
            {sectors.map((sector, index) => (
              <div key={sector}>
                <span>0{index + 1}</span>
                <strong>{sector}</strong>
                <i aria-hidden="true">↗</i>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="contact-section" id="contact">
        <div className="contact-copy">
          <span className="eyebrow">Première consultation</span>
          <h2>Parlons de ce qui compte pour vous.</h2>
          <p>
            Décrivez-nous brièvement votre besoin. Votre message sera préparé
            dans votre messagerie pour un envoi direct au cabinet.
          </p>

          <div className="contact-details">
            <a href="tel:+213541310255">
              <small>Téléphone</small>
              <strong>+213 (0) 541 31 02 55</strong>
            </a>
            <a href="mailto:rahim@aouiz-dz.com">
              <small>Email</small>
              <strong>rahim@aouiz-dz.com</strong>
            </a>
            <div>
              <small>Cabinet</small>
              <strong>Djasr Kasentina, Alger</strong>
            </div>
          </div>
        </div>

        <form className="contact-form" onSubmit={handleSubmit}>
          <div className="form-row">
            <label>
              <span>Votre nom *</span>
              <input name="name" type="text" placeholder="Nom et prénom" required />
            </label>
            <label>
              <span>Entreprise</span>
              <input name="company" type="text" placeholder="Nom de la société" />
            </label>
          </div>
          <label>
            <span>Email professionnel *</span>
            <input
              name="email"
              type="email"
              placeholder="vous@entreprise.dz"
              required
            />
          </label>
          <label>
            <span>Votre besoin *</span>
            <select name="need" defaultValue="" required>
              <option value="" disabled>Sélectionner une expertise</option>
              <option>Audit légal / Commissariat aux comptes</option>
              <option>Expertise comptable</option>
              <option>Conseil fiscal</option>
              <option>Conseil en gestion</option>
              <option>Autre besoin</option>
            </select>
          </label>
          <button className="button button-primary" type="submit">
            Préparer ma demande <span aria-hidden="true">→</span>
          </button>
          <small className="form-note">
            Vos informations restent confidentielles et ne sont pas stockées sur ce site.
          </small>
        </form>
      </section>

      <footer>
        <div className="footer-main">
          <a className="brand" href="#accueil" aria-label="Aiouez — Retour en haut">
            <span className="brand-mark" aria-hidden="true">A</span>
            <span className="brand-copy">
              <strong>Aiouez</strong>
              <small>Commissaire aux comptes</small>
            </span>
          </a>
          <p>
            Audit · Expertise comptable · Fiscalité · Conseil
            <br />
            Alger, Algérie
          </p>
          <a className="footer-arrow" href="#accueil" aria-label="Retour en haut">
            ↑
          </a>
        </div>
        <div className="footer-legal">
          <span>© 2026 Cabinet Aiouez. Tous droits réservés.</span>
          <span>Rigueur · Clarté · Confiance</span>
        </div>
      </footer>
    </main>
  );
}
