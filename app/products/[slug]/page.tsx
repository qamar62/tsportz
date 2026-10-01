import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft, ArrowUpRight, Check } from "lucide-react";
import { getProduct, getProducts } from "@/lib/wordpress";
import { ProductCard } from "@/components/product-card";

export async function generateStaticParams() { return (await getProducts()).map(product => ({ slug: product.slug })); }
export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> { const { slug } = await params; const product = await getProduct(slug); return { title: product?.name ?? "Product", description: product?.description }; }

export default async function ProductDetail({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const products = await getProducts();
  const product = products.find(item => item.slug === slug);
  if (!product) notFound();
  const index = products.indexOf(product);
  const related = products.filter(item => item.slug !== slug && item.category === product.category).slice(0, 3);
  const shownRelated = related.length >= 3 ? related : products.filter(item => item.slug !== slug).slice(0, 3);
  const message = encodeURIComponent(`Hello TABEER SPORTZ, I'd like to inquire about ${product.name}.`);
  return <><section className="detail-section"><div className="container"><Link href="/products" className="back-link"><ArrowLeft size={17} /> Back to products</Link><div className="detail-grid"><div className={`detail-art product-tone-${index % 6}${product.image ? " has-photo" : ""}`}>{product.image ? <Image src={product.image.url} alt={product.image.alt} fill sizes="(max-width: 650px) 100vw, 50vw" className="detail-photo" priority /> : <span className="detail-symbol" aria-hidden="true">{product.icon}</span>}<span className="detail-art-number">{product.number} / 13</span><span className="detail-art-label">TABEER SPORTZ / {product.category.toUpperCase()}</span></div><div className="detail-content"><span className="section-kicker"><span className="tiny-square" /> {product.category}</span><h1>{product.name}<span>.</span></h1><p className="detail-lead">{product.description}</p><div className="detail-line" /><h2>Product highlights</h2><ul>{product.details.map(detail => <li key={detail}><Check size={18} /> {detail}</li>)}</ul><p className="detail-note">Looking for specific colors, quantities or customization? Tell us what your team needs.</p><a className="btn btn-teal" href={`https://wa.me/923353631555?text=${message}`} target="_blank" rel="noopener noreferrer">Inquire about this product <ArrowUpRight size={18} /></a></div></div></div></section><section className="section related-section container"><div className="section-heading-row"><div><div className="section-kicker"><span className="tiny-square" /> KEEP EXPLORING</div><h2 className="display-heading">MORE TO <span>DISCOVER.</span></h2></div><Link href="/products" className="text-link">All products <ArrowUpRight size={18} /></Link></div><div className="featured-grid">{shownRelated.map(item => <ProductCard key={item.slug} product={item} index={products.indexOf(item)} />)}</div></section></>;
}
