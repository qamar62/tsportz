import type { Product } from "./products";
import { products as fallbackProducts } from "./products";

type WordPressTerm = { id: number; name: string; slug: string };
type WordPressImage = {
  id: number;
  url: string;
  width: number;
  height: number;
  alt: string;
  medium?: string;
};
type WordPressProduct = {
  id: number;
  slug: string;
  title: string;
  excerpt: string;
  image: WordPressImage | null;
  featured: boolean;
  categories: WordPressTerm[];
  specifications: Record<string, string | number | boolean>;
};

type WordPressProductList = { items: WordPressProduct[]; total: number };

const apiBase = () => (process.env.WORDPRESS_API_URL || "").replace(/\/$/, "");

function cleanText(value: string) {
  return value.replace(/<[^>]+>/g, " ").replace(/&amp;/g, "&").replace(/&#8211;|&ndash;/g, "–").replace(/\s+/g, " ").trim();
}

function mapProduct(item: WordPressProduct, index: number): Product {
  const fallback = fallbackProducts.find(product => product.slug === item.slug);
  const specs = item.specifications || {};
  const details = [
    specs.material && `Material: ${specs.material}`,
    specs.construction && `Construction: ${specs.construction}`,
    specs.moq && `Minimum order: ${specs.moq} pieces`,
    specs.lead_time && `Lead time: ${specs.lead_time}`,
  ].filter((detail): detail is string => Boolean(detail));

  return {
    wpId: item.id,
    image: item.image ? {
      url: item.image.url,
      alt: cleanText(item.image.alt) || cleanText(item.title),
      width: item.image.width,
      height: item.image.height,
    } : undefined,
    slug: item.slug,
    name: cleanText(item.title),
    category: (item.categories?.[0]?.name || fallback?.category || "Team Uniforms") as Product["category"],
    number: String(index + 1).padStart(2, "0"),
    icon: fallback?.icon || "✦",
    description: cleanText(item.excerpt) || fallback?.description || "Custom sports manufacturing for teams and private-label buyers.",
    details: details.length ? details : fallback?.details || ["Custom team colors", "Manufacturing options available", "Ask about minimum quantities"],
  };
}

export async function getProducts(): Promise<Product[]> {
  const base = apiBase();
  if (!base) return fallbackProducts;

  try {
    const response = await fetch(`${base}/products?per_page=100`, { next: { revalidate: 5 } });
    if (!response.ok) throw new Error(`WordPress returned ${response.status}`);
    const payload = await response.json() as WordPressProductList;
    return payload.items.map(mapProduct);
  } catch (error) {
    console.error("Using local product fallback because the WordPress API is unavailable.", error);
    return fallbackProducts;
  }
}

export async function getProduct(slug: string): Promise<Product | undefined> {
  const products = await getProducts();
  return products.find(product => product.slug === slug);
}

export function getWordPressApiBase() {
  return apiBase();
}
