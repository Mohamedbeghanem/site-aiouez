import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

const baseUrl = "https://aiouez.com";

export const metadata: Metadata = {
  metadataBase: new URL(baseUrl),
  title: "Cabinet Aiouez | Commissaire aux comptes à Alger",
  description:
    "Commissariat aux comptes, expertise comptable, fiscalité et conseil en gestion pour les entreprises en Algérie.",
  icons: {
    icon: "/logo-aiouez.svg",
    shortcut: "/logo-aiouez.svg",
  },
  openGraph: {
    title: "Cabinet Aiouez — Vos comptes méritent un regard de confiance",
    description:
      "Commissaire aux comptes et comptable agréé à Alger.",
    type: "website",
    locale: "fr_DZ",
    url: baseUrl,
    siteName: "Cabinet Aiouez",
    images: [
      {
        url: "/og.png",
        width: 1200,
        height: 630,
        alt: "Cabinet Aiouez — Audit, expertise comptable et conseil",
      },
    ],
  },
  twitter: {
    card: "summary_large_image",
    title: "Cabinet Aiouez — Audit & Conseil",
    description: "Vos comptes méritent un regard de confiance.",
    images: ["/og.png"],
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="fr">
      <body className={`${geistSans.variable} ${geistMono.variable}`}>
        {children}
      </body>
    </html>
  );
}
