"use client";

import { Search, X } from "lucide-react";
import { useMemo, useState } from "react";
import { ProductCard } from "./product-card";
import type { Product } from "@/lib/products";

export function Catalog({ products }: { products: Product[] }) {
  const categories = ["All Products", ...Array.from(new Set(products.map(product => product.category)))];
  const [category, setCategory] = useState("All Products");
  const [query, setQuery] = useState("");
  const filtered = useMemo(() => products.filter(product => (category === "All Products" || product.category === category) && product.name.toLowerCase().includes(query.trim().toLowerCase())), [category, query]);
  return <section className="catalog-section section"><div className="container"><div className="catalog-toolbar"><div className="filter-list" aria-label="Filter products by category">{categories.map(item => <button key={item} type="button" className={category === item ? "selected" : ""} onClick={() => setCategory(item)}>{item}</button>)}</div><label className="catalog-search"><Search size={18} /><input value={query} onChange={event => setQuery(event.target.value)} placeholder="Search products" aria-label="Search products" />{query && <button type="button" aria-label="Clear search" onClick={() => setQuery("")}><X size={16} /></button>}</label></div><div className="catalog-count">SHOWING {filtered.length.toString().padStart(2, "0")} / {products.length.toString().padStart(2, "0")} PRODUCTS</div>{filtered.length ? <div className="catalog-grid">{filtered.map(product => <ProductCard key={product.slug} product={product} index={products.indexOf(product)} />)}</div> : <div className="empty-state"><h2>No products found.</h2><p>Try another search or category.</p><button type="button" className="text-link" onClick={() => { setQuery(""); setCategory("All Products"); }}>Show all products</button></div>}</div></section>;
}
