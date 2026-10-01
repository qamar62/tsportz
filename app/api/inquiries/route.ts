import { getWordPressApiBase } from "@/lib/wordpress";

export async function POST(request: Request) {
  const base = getWordPressApiBase();
  if (!base) return Response.json({ message: "CMS is not configured." }, { status: 503 });

  try {
    const body = await request.json();
    const response = await fetch(`${base}/inquiries`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
      cache: "no-store",
    });
    const payload = await response.json();
    return Response.json(payload, { status: response.status });
  } catch {
    return Response.json({ message: "The inquiry service is unavailable." }, { status: 502 });
  }
}
