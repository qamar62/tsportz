import type { Metadata } from "next";
import { Header } from "@/components/header";
import { Footer } from "@/components/footer";
import { FloatingChat } from "@/components/floating-chat";
import "./globals.css";

export const metadata: Metadata = {
  title: { default: "TABEER SPORTZ | Built for Every Game", template: "%s | TABEER SPORTZ" },
  description: "Explore custom sports uniforms, teamwear and equipment from TABEER SPORTZ. Built for teams that play with purpose.",
  icons: { icon: "/logo.png", apple: "/logo.png" },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en" suppressHydrationWarning><head><script dangerouslySetInnerHTML={{ __html: "try{var theme=localStorage.getItem('tabeer-theme');document.documentElement.dataset.theme=theme==='dark'?'dark':'light'}catch(e){document.documentElement.dataset.theme='light'}" }} /></head><body><Header /><main>{children}</main><Footer /><FloatingChat /></body></html>;
}
