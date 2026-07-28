import assert from "node:assert/strict";
import { access, readFile } from "node:fs/promises";
import test from "node:test";

const projectRoot = new URL("../", import.meta.url);

async function render(path = "/") {
  const workerUrl = new URL("../dist/server/index.js", import.meta.url);
  workerUrl.searchParams.set("test", `${process.pid}-${Date.now()}`);
  const { default: worker } = await import(workerUrl.href);

  return worker.fetch(
    new Request(`https://cabinet-aiouez.example${path}`, {
      headers: { accept: "text/html", host: "cabinet-aiouez.example" },
    }),
    {
      ASSETS: {
        fetch: async () => new Response("Not found", { status: 404 }),
      },
    },
    {
      waitUntil() {},
      passThroughOnException() {},
    },
  );
}

test("server-renders the finished Cabinet Aiouez site", async () => {
  const response = await render();
  assert.equal(response.status, 200);
  assert.match(response.headers.get("content-type") ?? "", /^text\/html\b/i);

  const html = await response.text();
  assert.match(html, /<html[^>]+lang="fr"/i);
  assert.match(html, /Cabinet Aiouez \| Commissaire aux comptes à Alger/);
  assert.match(html, /Vos comptes méritent/);
  assert.match(html, /Commissaire aux comptes/);
  assert.match(html, /Commissariat aux comptes/);
  assert.match(html, /Expertise comptable/);
  assert.match(html, /Conseil fiscal/);
  assert.match(html, /Djasr Kasentina, Alger/);
  assert.match(html, /rahim@aouiz-dz\.com/);
  assert.doesNotMatch(html, /codex-preview|Building your site|SkeletonPreview/);
});

test("ships the bespoke social card and removes starter dependencies", async () => {
  const [layout, packageJson] = await Promise.all([
    readFile(new URL("../app/layout.tsx", import.meta.url), "utf8"),
    readFile(new URL("../package.json", import.meta.url), "utf8"),
  ]);

  await access(new URL("../public/og.png", import.meta.url));
  assert.match(layout, /\/og\.png/);
  assert.doesNotMatch(packageJson, /react-loading-skeleton/);
  await assert.rejects(access(new URL("../app/_sites-preview/SkeletonPreview.tsx", import.meta.url)));
  await assert.rejects(access(new URL("../app/_sites-preview/preview.css", import.meta.url)));
  await access(projectRoot);
});
