export type Product = {
  wpId?: number;
  image?: {
    url: string;
    alt: string;
    width: number;
    height: number;
  };
  slug: string;
  name: string;
  category: "Team Uniforms" | "Training & Apparel" | "Equipment" | "Combat Sports";
  number: string;
  icon: string;
  description: string;
  details: string[];
};

// Replace this array with mapped WordPress REST API data when the backend is connected.
export const products: Product[] = [
  { slug: "american-football-uniform", name: "American Football Uniform", category: "Team Uniforms", number: "01", icon: "🏈", description: "Built for the intensity of every down, with a team look that stands out.", details: ["Custom team colors and graphics", "Durable game-ready construction", "Sizing for complete squads"] },
  { slug: "soccer-uniform", name: "Soccer Uniform", category: "Team Uniforms", number: "02", icon: "⚽", description: "A sharp, unified look made for ninety minutes and beyond.", details: ["Lightweight performance feel", "Custom names, numbers and crests", "Home and away concepts"] },
  { slug: "tracksuit", name: "Tracksuit", category: "Training & Apparel", number: "03", icon: "◈", description: "From warm-up to travel, a coordinated essential for every team.", details: ["Comfortable athletic fit", "Jacket and pant sets", "Team color options"] },
  { slug: "baseball-uniform", name: "Baseball Uniform", category: "Team Uniforms", number: "04", icon: "⚾", description: "Classic diamond style with a confident, contemporary finish.", details: ["Custom jerseys and pants", "Team lettering and numbering", "Built for movement"] },
  { slug: "basketball-uniform", name: "Basketball Uniform", category: "Team Uniforms", number: "05", icon: "🏀", description: "Light, expressive kits for fast breaks and big moments.", details: ["Jersey and short sets", "Bold custom graphics", "Breathable game feel"] },
  { slug: "football-soccer-ball", name: "Football / Soccer Ball", category: "Equipment", number: "06", icon: "⚽", description: "The center of every session, match and memorable goal.", details: ["Team and club branding options", "Training and match concepts", "Made for consistent play"] },
  { slug: "goalkeeper-gloves", name: "Goalkeeper Gloves", category: "Equipment", number: "07", icon: "✦", description: "Confident grip and a striking look between the posts.", details: ["Comfort-focused fit", "Distinctive colorways", "Options for clubs and teams"] },
  { slug: "ice-hockey-uniform", name: "Ice Hockey Uniform", category: "Team Uniforms", number: "08", icon: "❄", description: "A powerful on-ice identity for teams that play all in.", details: ["Custom jerseys and socks", "Player names and numbers", "Team-first design"] },
  { slug: "polo-shirts", name: "Polo Shirts", category: "Training & Apparel", number: "09", icon: "◉", description: "A polished off-field staple for coaches, staff and players.", details: ["Clean branded finish", "Multiple color options", "Comfortable everyday wear"] },
  { slug: "windbreaker-jackets", name: "Windbreaker Jackets", category: "Training & Apparel", number: "10", icon: "↗", description: "An easy outer layer for the sidelines, the commute and the elements.", details: ["Lightweight layering", "Team branding options", "Athletic silhouette"] },
  { slug: "sports-shorts", name: "Sports Shorts", category: "Training & Apparel", number: "11", icon: "↘", description: "Move freely through training days and game days.", details: ["Easy-moving fit", "Coordinated team colors", "Versatile sport use"] },
  { slug: "bjj-gears", name: "BJJ Gears", category: "Combat Sports", number: "12", icon: "✳", description: "Purposeful gear for the discipline and energy of the mat.", details: ["Training-focused designs", "Club identity options", "Comfort for active sessions"] },
  { slug: "polo-uniform", name: "Polo Uniform", category: "Team Uniforms", number: "13", icon: "♞", description: "A refined team presence inspired by the pace of polo.", details: ["Coordinated team styling", "Custom colors and marks", "Comfortable performance fit"] }
];

export const categories = ["All Products", "Team Uniforms", "Training & Apparel", "Equipment", "Combat Sports"] as const;
