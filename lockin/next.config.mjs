/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // pg is server-only; keep it out of the client/edge bundles.
  serverExternalPackages: ["pg"],
};

export default nextConfig;
