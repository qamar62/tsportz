import type { Metadata } from "next";
import { Catalog } from "@/components/catalog";
import { getProducts } from "@/lib/wordpress";

export const metadata: Metadata = { title: "Products", description: "Explore all TABEER SPORTZ uniforms, teamwear and sports equipment." };

export default async function ProductsPage() {
  const products = await getProducts();
  return <><section className="page-hero products-hero"><div className="container page-hero-inner"><span className="eyebrow"><span className="eyebrow-line" /> THE COLLECTION</span><h1>FIND YOUR<br /><em>GAME.</em></h1><p>Explore uniforms, apparel and equipment for every kind of team.</p></div><span className="page-hero-number">01 / 03</span></section><Catalog products={products} /></>;
}
