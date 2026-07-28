import type { Metadata } from "next";
import { headers } from "next/headers";
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

export async function generateMetadata(): Promise<Metadata> {
  const requestHeaders = await headers();
  const protocol = requestHeaders.get("x-forwarded-proto") ?? "https";
  const host =
    requestHeaders.get("x-forwarded-host") ??
    requestHeaders.get("host") ??
    "aiouez.com";
  const baseUrl = `${protocol}://${host}`;

  return {
    title: "Cabinet Aiouez | Commissaire aux comptes à Alger",
    description:
      "Audit légal, expertise comptable, fiscalité et conseil en gestion pour les entreprises en Algérie.",
    openGraph: {
      title: "Cabinet Aiouez — La clarté financière au service de vos décisions",
      description:
        "Audit, expertise comptable et conseil pour avancer avec des chiffres fiables et une vision nette.",
      type: "website",
      locale: "fr_DZ",
      url: baseUrl,
      siteName: "Cabinet Aiouez",
      images: [
        {
          url: `${baseUrl}/og.png`,
          width: 1200,
          height: 630,
          alt: "Cabinet Aiouez — Audit, expertise comptable et conseil",
        },
      ],
    },
    twitter: {
      card: "summary_large_image",
      title: "Cabinet Aiouez — Audit & Conseil",
      description: "La clarté financière au service de vos décisions.",
      images: [`${baseUrl}/og.png`],
    },
  };
}

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
