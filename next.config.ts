import type { NextConfig } from "next";

const wordpressImagePattern = new URL(
  "/wp-content/uploads/**",
  process.env.WORDPRESS_API_URL || "http://tabeer-sportz.local",
);
const isLocalWordPress = wordpressImagePattern.hostname === "localhost"
  || wordpressImagePattern.hostname === "127.0.0.1"
  || wordpressImagePattern.hostname.endsWith(".local");

const nextConfig: NextConfig = {
  outputFileTracingRoot: process.cwd(),
  images: {
    remotePatterns: [wordpressImagePattern],
    dangerouslyAllowLocalIP: isLocalWordPress,
  },
};

export default nextConfig;
