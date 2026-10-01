"use client";

import { ArrowUpRight } from "lucide-react";
import { FormEvent, useState } from "react";
import type { Product } from "@/lib/products";

export function ContactForm({ products }: { products: Product[] }) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [product, setProduct] = useState("");
  const [message, setMessage] = useState("");
  const [sending, setSending] = useState(false);
  const [status, setStatus] = useState<"idle" | "sent" | "error">("idle");

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSending(true);
    setStatus("idle");
    const selected = products.find(item => item.slug === product);
    try {
      const response = await fetch("/api/inquiries", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, email, message, product_id: selected?.wpId || 0, website: "" }),
      });
      if (!response.ok) throw new Error("Request failed");
      setStatus("sent");
    } catch {
      setStatus("error");
    } finally {
      setSending(false);
    }
  }

  const selected = products.find(item => item.slug === product);
  const whatsappBody = [`Hello TABEER SPORTZ,`, `Name: ${name}`, `Email: ${email}`, selected && `Product: ${selected.name}`, `Message: ${message}`].filter(Boolean).join("\n");

  return <form className="contact-form" onSubmit={submit}>
    <span className="form-heading">TELL US ABOUT YOUR PROJECT <span>↗</span></span>
    <div className="form-row"><label>Your name <input required value={name} onChange={event => setName(event.target.value)} placeholder="Your full name" autoComplete="name" /></label><label>Email address <input required type="email" value={email} onChange={event => setEmail(event.target.value)} placeholder="you@example.com" autoComplete="email" /></label></div>
    <label>Interested in <select value={product} onChange={event => setProduct(event.target.value)}><option value="">Select a product (optional)</option>{products.map(item => <option key={item.slug} value={item.slug}>{item.name}</option>)}</select></label>
    <label>Message <textarea required rows={5} value={message} onChange={event => setMessage(event.target.value)} placeholder="Tell us about your team, quantity, colors or any questions..." /></label>
    <button type="submit" className="btn btn-teal" disabled={sending}>{sending ? "Sending..." : "Send inquiry"} <ArrowUpRight size={18} /></button>
    {status === "sent" && <div className="form-success" role="status"><strong>Thank you. Your inquiry is saved.</strong><a href={`https://wa.me/923353631555?text=${encodeURIComponent(whatsappBody)}`} target="_blank" rel="noopener noreferrer">Continue on WhatsApp <ArrowUpRight size={15} /></a></div>}
    {status === "error" && <small className="form-error" role="alert">We could not save the inquiry. Please use the WhatsApp chat button.</small>}
    <small className="form-note">Your inquiry will be saved securely for our team.</small>
  </form>;
}
