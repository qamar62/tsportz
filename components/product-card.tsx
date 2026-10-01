import Link from "next/link";
import Image from "next/image";
import { ArrowUpRight } from "lucide-react";
import type { Product } from "@/lib/products";

export function ProductCard({ product, index = 0 }: { product: Product; index?: number }) {
  return <Link href={`/products/${product.slug}`} className={`product-card product-tone-${index % 6}${product.image ? " has-photo" : ""}`}>
    {product.image ? <Image src={product.image.url} alt={product.image.alt} fill sizes="(max-width: 650px) 100vw, (max-width: 900px) 50vw, 33vw" className="product-photo" /> : null}
    <div className="product-card-top"><span>{product.number} / 13</span><span className="product-arrow"><ArrowUpRight size={19} /></span></div>
    {!product.image && <div className="product-symbol" aria-hidden="true">{product.icon}</div>}
    <div className="product-card-bottom"><span className="eyebrow">{product.category}</span><h3>{product.name}</h3><span className="product-view">Explore product <ArrowUpRight size={15} /></span></div>
  </Link>;
}
