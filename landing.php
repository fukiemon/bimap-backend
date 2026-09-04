<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BiMAP — Barangay Integrated Monitoring & Alert Platform | Malita, Davao Occidental</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --blue: #1a73e8;
  --blue-dark: #0d47a1;
  --blue-deep: #003c8f;
  --gold: #c8a55a;
  --gold-light: #e8c97a;
  --white: #fff;
  --off-white: #f4f7fc;
  --text: #0d1b2a;
  --muted: #6b7a99;
  --border: #dde3ed;
  --green: #2e7d32;
}

html { scroll-behavior: smooth; }
body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: var(--text);
  background: var(--white);
  overflow-x: hidden;
}

/* ======== NAVBAR ======== */
nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 1000;
  background: rgba(13, 28, 58, 0.97);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(200, 165, 90, 0.2);
  padding: 0 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 68px;
}

.nav-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}

.nav-logo {
  width: 44px;
  height: 44px;
  object-fit: contain;
  filter: drop-shadow(0 2px 6px rgba(0,0,0,0.3));
}

.nav-title { display: flex; flex-direction: column; line-height: 1.1; }
.nav-title .brand-name { font-size: 18px; font-weight: 900; color: #fff; letter-spacing: 0.5px; }
.nav-title .brand-sub { font-size: 10px; font-weight: 600; color: var(--gold); letter-spacing: 1px; text-transform: uppercase; }

.nav-links { display: flex; gap: 28px; list-style: none; }
.nav-links a { color: rgba(255,255,255,0.75); text-decoration: none; font-size: 13.5px; font-weight: 600; transition: color 0.2s; letter-spacing: 0.3px; }
.nav-links a:hover { color: var(--gold); }

.nav-actions { display: flex; gap: 10px; align-items: center; }

.btn-nav-login {
  padding: 9px 22px; border-radius: 8px; font-size: 13.5px; font-weight: 700;
  font-family: 'Plus Jakarta Sans', sans-serif; cursor: pointer; transition: all 0.2s;
  text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
}

.btn-nav-login.outline { border: 1.5px solid rgba(255,255,255,0.35); color: rgba(255,255,255,0.85); background: transparent; }
.btn-nav-login.outline:hover { border-color: var(--gold); color: var(--gold); }
.btn-nav-login.solid { background: linear-gradient(135deg, #1a73e8, #0d47a1); color: white; border: none; }
.btn-nav-login.solid:hover { background: linear-gradient(135deg, #2280f0, #1557c0); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(26, 115, 232, 0.4); }

/* ======== HERO ======== */
.hero {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  overflow: hidden;
  background: linear-gradient(160deg, #0d1c3a 0%, #0d47a1 50%, #1a73e8 100%);
}

.hero-bg-pattern {
  position: absolute; inset: 0; opacity: 0.06;
  background-image: 
    radial-gradient(circle at 20% 50%, rgba(255,255,255,0.4) 1px, transparent 1px),
    radial-gradient(circle at 80% 20%, rgba(255,255,255,0.3) 1px, transparent 1px);
  background-size: 60px 60px, 40px 40px;
}

.hero-glow-1 { position: absolute; width: 500px; height: 500px; border-radius: 50%; background: radial-gradient(circle, rgba(200,165,90,0.12) 0%, transparent 70%); top: -100px; right: -100px; pointer-events: none; }
.hero-glow-2 { position: absolute; width: 400px; height: 400px; border-radius: 50%; background: radial-gradient(circle, rgba(26,115,232,0.25) 0%, transparent 70%); bottom: -80px; left: -80px; pointer-events: none; }

.hero-inner {
  position: relative; z-index: 2;
  max-width: 1200px; margin: 0 auto;
  padding: 100px 40px 80px;
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 60px; align-items: center;
}

.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(200,165,90,0.15); border: 1px solid rgba(200,165,90,0.35);
  border-radius: 100px; padding: 6px 16px; font-size: 12px; font-weight: 700;
  color: var(--gold); letter-spacing: 0.8px; text-transform: uppercase; margin-bottom: 24px;
}
.hero-badge i { font-size: 11px; }

.hero-title { font-family: 'Playfair Display', serif; font-size: 56px; font-weight: 800; color: white; line-height: 1.08; margin-bottom: 20px; letter-spacing: -0.5px; }
.hero-title .highlight { color: var(--gold); display: block; }

.hero-subtitle { font-size: 17px; color: rgba(255,255,255,0.72); line-height: 1.7; margin-bottom: 36px; font-weight: 500; max-width: 460px; }

.hero-actions { display: flex; gap: 14px; flex-wrap: wrap; }

.btn-hero {
  padding: 14px 30px; border-radius: 10px; font-size: 15px; font-weight: 700;
  font-family: 'Plus Jakarta Sans', sans-serif; cursor: pointer; transition: all 0.25s;
  text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
}
.btn-hero.primary { background: linear-gradient(135deg, #1a73e8, #0d47a1); color: white; border: none; box-shadow: 0 8px 24px rgba(26,115,232,0.45); }
.btn-hero.primary:hover { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(26,115,232,0.55); }
.btn-hero.secondary { background: rgba(255,255,255,0.1); color: white; border: 1.5px solid rgba(255,255,255,0.3); backdrop-filter: blur(8px); }
.btn-hero.secondary:hover { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.5); transform: translateY(-2px); }

.hero-stats { display: flex; gap: 32px; margin-top: 48px; padding-top: 36px; border-top: 1px solid rgba(255,255,255,0.1); }
.hero-stat .stat-num { font-size: 30px; font-weight: 900; color: white; line-height: 1; }
.hero-stat .stat-label { font-size: 12px; color: rgba(255,255,255,0.55); font-weight: 600; margin-top: 4px; letter-spacing: 0.5px; }

/* Hero visual - Mayor photo card */
.hero-visual { display: flex; justify-content: center; align-items: center; position: relative; }

.mayor-card {
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 28px;
  padding: 28px;
  backdrop-filter: blur(12px);
  width: 300px;
  text-align: center;
  box-shadow: 0 30px 80px rgba(0,0,0,0.35);
  animation: phoneFloat 4s ease-in-out infinite;
}

@keyframes phoneFloat {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-12px); }
}

.mayor-photo {
  width: 140px;
  height: 140px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid var(--gold);
  margin: 0 auto 16px;
  display: block;
  box-shadow: 0 8px 28px rgba(0,0,0,0.3);
}

.mayor-card-name { color: white; font-size: 16px; font-weight: 800; margin-bottom: 4px; }
.mayor-card-title { color: var(--gold); font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 16px; }

.mayor-seal {
  width: 56px;
  height: 56px;
  margin: 0 auto;
  border-radius: 50%;
  background: rgba(255,255,255,0.1);
  border: 2px solid rgba(200,165,90,0.4);
  display: flex; align-items: center; justify-content: center;
  font-size: 26px;
}

.mayor-card-footer {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid rgba(255,255,255,0.1);
  font-size: 11px;
  color: rgba(255,255,255,0.5);
  font-weight: 600;
  letter-spacing: 0.5px;
}

.hero-glow-ring {
  position: absolute; width: 340px; height: 340px; border-radius: 50%;
  border: 1px solid rgba(200,165,90,0.2); top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  animation: ringPulse 3s ease-in-out infinite;
}

@keyframes ringPulse {
  0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.4; }
  50% { transform: translate(-50%, -50%) scale(1.06); opacity: 0.15; }
}

/* ======== LGU BANNER ======== */
.lgu-banner {
  background: linear-gradient(135deg, #0d1c3a, #0d2b5a);
  border-top: 3px solid var(--gold);
  border-bottom: 3px solid var(--gold);
  padding: 28px 40px; overflow: hidden; position: relative;
}

.lgu-banner::before {
  content: ''; position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23c8a55a' fill-opacity='0.04'%3E%3Cpath d='M0 40L40 0H20L0 20M40 40V20L20 40'/%3E%3C/g%3E%3C/svg%3E") repeat;
}

.lgu-banner-inner {
  position: relative; max-width: 1200px; margin: 0 auto;
  display: flex; align-items: center; gap: 24px;
  justify-content: center; flex-wrap: wrap;
}

.lgu-logo-wrap { display: flex; align-items: center; gap: 16px; }

.lgu-seal-img {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  border: 2px solid var(--gold);
  object-fit: cover;
  flex-shrink: 0;
}

.lgu-text .lgu-label { font-size: 11px; font-weight: 700; color: var(--gold); letter-spacing: 2px; text-transform: uppercase; }
.lgu-text .lgu-name { font-size: 20px; font-weight: 900; color: white; line-height: 1.1; }
.lgu-text .lgu-loc { font-size: 12.5px; color: rgba(255,255,255,0.6); font-weight: 600; margin-top: 2px; }

.lgu-divider { width: 1px; height: 50px; background: rgba(200,165,90,0.3); }

.lgu-tagline { font-size: 15px; color: rgba(255,255,255,0.8); font-weight: 600; font-style: italic; max-width: 380px; text-align: center; }

/* ======== SECTIONS ======== */
.section { padding: 96px 40px; }
.section-inner { max-width: 1200px; margin: 0 auto; }

.section-eyebrow {
  font-size: 12px; font-weight: 800; color: var(--blue); letter-spacing: 2px;
  text-transform: uppercase; margin-bottom: 12px; display: flex; align-items: center; gap: 10px;
}
.section-eyebrow::before { content: ''; display: block; width: 28px; height: 2px; background: var(--blue); border-radius: 2px; }
.section-title { font-family: 'Playfair Display', serif; font-size: 40px; font-weight: 800; color: var(--text); line-height: 1.15; margin-bottom: 16px; }
.section-desc { font-size: 16.5px; color: var(--muted); line-height: 1.75; max-width: 620px; font-weight: 500; }

/* About section */
.about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }

.about-image-wrap { position: relative; }

.about-img-card {
  border-radius: 24px;
  overflow: hidden;
  aspect-ratio: 4/3;
  position: relative;
  box-shadow: 0 20px 60px rgba(13,71,161,0.3);
}

.about-img-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.about-img-overlay {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  background: linear-gradient(to top, rgba(13,28,58,0.88) 0%, transparent 70%);
  padding: 24px 20px 20px;
  color: white;
}

.about-img-overlay .overlay-title { font-size: 16px; font-weight: 800; margin-bottom: 4px; }
.about-img-overlay .overlay-sub { font-size: 12px; color: rgba(255,255,255,0.7); font-weight: 600; }

.about-badge {
  position: absolute; bottom: -20px; right: -20px;
  background: var(--gold); color: #0d1c3a; border-radius: 16px;
  padding: 14px 18px; font-size: 13px; font-weight: 800;
  display: flex; align-items: center; gap: 8px;
  box-shadow: 0 8px 24px rgba(200,165,90,0.4);
}

.about-info-list { list-style: none; display: flex; flex-direction: column; gap: 14px; margin-top: 28px; margin-bottom: 36px; }
.about-info-list li { display: flex; align-items: flex-start; gap: 14px; font-size: 15px; color: var(--muted); font-weight: 500; line-height: 1.5; }
.about-info-list li .icon { width: 38px; height: 38px; border-radius: 10px; background: #e8f0fe; display: flex; align-items: center; justify-content: center; color: var(--blue); font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.about-info-list li strong { color: var(--text); display: block; font-weight: 700; font-size: 14px; margin-bottom: 2px; }

/* ======== MAYOR SECTION ======== */
.mayor-section {
  background: var(--off-white);
  padding: 64px 40px;
}

.mayor-section-inner {
  max-width: 1200px; margin: 0 auto;
  display: flex; align-items: center; gap: 60px;
}

.mayor-photo-wrap {
  flex-shrink: 0;
  text-align: center;
}

.mayor-full-photo {
  width: 200px;
  height: 240px;
  object-fit: cover;
  object-position: top;
  border-radius: 20px;
  box-shadow: 0 16px 48px rgba(13,71,161,0.2);
  border: 4px solid white;
  display: block;
}

.mayor-details { flex: 1; }
.mayor-eyebrow { font-size: 12px; font-weight: 800; color: var(--blue); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 8px; }
.mayor-name { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 800; color: var(--text); margin-bottom: 6px; }
.mayor-pos { font-size: 15px; font-weight: 700; color: var(--gold); margin-bottom: 14px; }
.mayor-desc { font-size: 15px; color: var(--muted); line-height: 1.75; max-width: 560px; }

/* ======== FEATURES ======== */
.features-section { background: var(--off-white); }

.features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 56px; }

.feature-card { background: white; border-radius: 20px; padding: 32px 28px; border: 1.5px solid var(--border); transition: all 0.3s ease; position: relative; overflow: hidden; }
.feature-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--blue), #1a73e8); opacity: 0; transition: opacity 0.3s; }
.feature-card:hover { transform: translateY(-6px); box-shadow: 0 20px 48px rgba(13,71,161,0.12); border-color: rgba(26,115,232,0.2); }
.feature-card:hover::before { opacity: 1; }

.feature-icon { width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #e8f0fe, #c2d4f8); display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--blue-dark); margin-bottom: 20px; }
.feature-title { font-size: 18px; font-weight: 800; color: var(--text); margin-bottom: 10px; line-height: 1.2; }
.feature-desc { font-size: 14px; color: var(--muted); line-height: 1.7; font-weight: 500; }

/* ======== SERVICES ======== */
.services-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 48px; }

.service-card { background: white; border-radius: 18px; padding: 28px 22px; text-align: center; border: 1.5px solid var(--border); transition: all 0.3s; cursor: default; }
.service-card:hover { border-color: var(--blue); box-shadow: 0 10px 32px rgba(26,115,232,0.12); transform: translateY(-4px); }
.service-icon-wrap { width: 64px; height: 64px; border-radius: 18px; background: linear-gradient(135deg, #1a73e8, #0d47a1); display: flex; align-items: center; justify-content: center; font-size: 26px; color: white; margin: 0 auto 16px; }
.service-title { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.service-desc { font-size: 12.5px; color: var(--muted); line-height: 1.6; font-weight: 500; }
.service-phone { font-size: 11.5px; color: var(--blue); font-weight: 700; margin-top: 8px; }

/* ======== NEWS ======== */
.news-section { background: var(--off-white); }
.news-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; margin-top: 48px; }

.news-featured { background: white; border-radius: 20px; overflow: hidden; border: 1.5px solid var(--border); transition: all 0.3s; }
.news-featured:hover { box-shadow: 0 16px 48px rgba(0,0,0,0.1); transform: translateY(-3px); }

.news-featured-img {
  height: 220px;
  overflow: hidden;
  position: relative;
}

.news-featured-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.news-featured-body { padding: 28px; }

.news-tag { display: inline-block; background: #e8f0fe; color: var(--blue); font-size: 11px; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase; border-radius: 6px; padding: 4px 10px; margin-bottom: 12px; }
.news-title { font-size: 20px; font-weight: 800; color: var(--text); line-height: 1.3; margin-bottom: 10px; }
.news-excerpt { font-size: 14px; color: var(--muted); line-height: 1.7; font-weight: 500; }
.news-meta { display: flex; align-items: center; gap: 16px; margin-top: 18px; font-size: 12.5px; color: var(--muted); font-weight: 600; }
.news-meta i { font-size: 12px; }

.news-list { display: flex; flex-direction: column; gap: 14px; }
.news-item { background: white; border-radius: 14px; padding: 20px; border: 1.5px solid var(--border); transition: all 0.25s; }
.news-item:hover { border-color: rgba(26,115,232,0.3); box-shadow: 0 6px 20px rgba(0,0,0,0.07); }
.news-item-tag { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--blue); margin-bottom: 6px; }
.news-item-title { font-size: 14px; font-weight: 700; color: var(--text); line-height: 1.4; margin-bottom: 6px; }
.news-item-date { font-size: 12px; color: var(--muted); font-weight: 600; display: flex; align-items: center; gap: 5px; }

/* ======== GALLERY STRIP ======== */
.gallery-section { padding: 80px 40px; }

.gallery-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-top: 48px;
}

.gallery-item {
  border-radius: 16px;
  overflow: hidden;
  aspect-ratio: 4/3;
  position: relative;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0,0,0,0.1);
  transition: all 0.3s;
}

.gallery-item:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.18); }
.gallery-item:hover .gallery-overlay { opacity: 1; }

.gallery-item img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.4s; }
.gallery-item:hover img { transform: scale(1.06); }

.gallery-overlay {
  position: absolute; inset: 0;
  background: rgba(13,71,161,0.7);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity 0.3s;
}
.gallery-overlay i { color: white; font-size: 24px; }

.gallery-label {
  position: absolute; bottom: 0; left: 0; right: 0;
  background: linear-gradient(to top, rgba(13,28,58,0.9) 0%, transparent 100%);
  padding: 20px 14px 12px;
  color: white; font-size: 11.5px; font-weight: 700;
}

/* ======== HOTLINES ======== */
.hotlines-header { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }

.hotlines-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 32px; }

.hotline-card { background: white; border-radius: 16px; padding: 22px 20px; border: 1.5px solid var(--border); display: flex; align-items: center; gap: 16px; transition: all 0.25s; }
.hotline-card:hover { border-color: rgba(26,115,232,0.3); box-shadow: 0 6px 20px rgba(0,0,0,0.07); transform: translateX(4px); }
.hotline-card .hc-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; flex-shrink: 0; }

.hc-icon.red { background: linear-gradient(135deg, #e53935, #c62828); }
.hc-icon.blue { background: linear-gradient(135deg, #1a73e8, #0d47a1); }
.hc-icon.orange { background: linear-gradient(135deg, #f57c00, #e65100); }
.hc-icon.green { background: linear-gradient(135deg, #43a047, #2e7d32); }
.hc-icon.purple { background: linear-gradient(135deg, #7b1fa2, #4a148c); }
.hc-icon.teal { background: linear-gradient(135deg, #00796b, #004d40); }

.hc-label { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.6px; }
.hc-name { font-size: 14.5px; font-weight: 800; color: var(--text); margin: 2px 0; }
.hc-num { font-size: 13px; color: var(--blue); font-weight: 700; }

/* Emergency hotline image */
.emergency-img-wrap {
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 16px 48px rgba(0,0,0,0.15);
  margin-bottom: 20px;
}
.emergency-img-wrap img { width: 100%; display: block; }

.hotlines-cta-wrap { display: flex; flex-direction: column; gap: 20px; }

.contact-block {
  background: linear-gradient(135deg, #0d1c3a, #0d2b5a);
  border-radius: 20px; padding: 32px 28px; color: white;
  border: 1px solid rgba(200,165,90,0.25);
}
.contact-block h3 { font-size: 20px; font-weight: 800; margin-bottom: 8px; }
.contact-block p { font-size: 14px; color: rgba(255,255,255,0.65); line-height: 1.6; font-weight: 500; }
.contact-detail { display: flex; align-items: center; gap: 10px; margin-top: 10px; font-size: 14px; color: rgba(255,255,255,0.8); font-weight: 600; }
.contact-detail i { color: var(--gold); font-size: 15px; }

/* ======== MENRO SPOTLIGHT ======== */
.menro-section {
  background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
  padding: 64px 40px;
  border-top: 3px solid #a5d6a7;
  border-bottom: 3px solid #a5d6a7;
}
.menro-section-inner {
  max-width: 1200px; margin: 0 auto;
  display: grid; grid-template-columns: 1fr 2fr; gap: 60px; align-items: start;
}
.menro-head-photo {
  width: 160px; height: 160px;
  border-radius: 50%; object-fit: cover; object-position: top;
  border: 4px solid white;
  box-shadow: 0 12px 36px rgba(0,100,0,0.2);
  display: block; margin: 0 auto 14px;
}
.menro-head-name { font-size: 16px; font-weight: 800; color: var(--text); text-align: center; margin-bottom: 4px; }
.menro-head-pos { font-size: 12px; color: #2e7d32; font-weight: 700; text-align: center; letter-spacing: 0.5px; }
.menro-content-title { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 800; color: var(--text); margin-bottom: 10px; }
.menro-eyebrow { font-size: 11px; font-weight: 800; color: #2e7d32; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 8px; }
.menro-visionmission { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
.menro-vm-card { background: white; border-radius: 14px; padding: 20px; border-left: 4px solid #43a047; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.menro-vm-card h4 { font-size: 13px; font-weight: 800; color: #2e7d32; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
.menro-vm-card p, .menro-vm-card li { font-size: 13.5px; color: var(--muted); line-height: 1.65; font-weight: 500; }
.menro-vm-card ul { padding-left: 16px; display: flex; flex-direction: column; gap: 6px; }
.menro-hours { margin-top: 18px; background: white; border-radius: 14px; padding: 18px 20px; display: inline-flex; align-items: center; gap: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); font-size: 13.5px; color: var(--muted); font-weight: 600; }
.menro-hours i { color: #2e7d32; font-size: 16px; }
.menro-staff-grid { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
.menro-staff-pill { background: white; border: 1.5px solid #c8e6c9; border-radius: 100px; padding: 8px 16px 8px 10px; display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; color: var(--text); }
.menro-staff-pill img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #a5d6a7; }
.menro-staff-pill span { display: flex; flex-direction: column; }
.menro-staff-pill .pill-role { font-size: 10px; color: #2e7d32; font-weight: 600; }

/* ======== CTA ======== */
.cta-section {
  background: linear-gradient(135deg, #0d1c3a 0%, #0d47a1 60%, #1a73e8 100%);
  padding: 96px 40px; text-align: center; position: relative; overflow: hidden;
}
.cta-section::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 50% 50%, rgba(200,165,90,0.08) 0%, transparent 70%); }
.cta-inner { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }
.cta-section .section-eyebrow { justify-content: center; color: var(--gold); }
.cta-section .section-eyebrow::before { background: var(--gold); }
.cta-section .section-title { color: white; }
.cta-section .section-desc { color: rgba(255,255,255,0.7); max-width: 100%; margin: 0 auto 40px; }

.cta-buttons { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
.btn-cta { padding: 15px 34px; border-radius: 10px; font-size: 15.5px; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; cursor: pointer; transition: all 0.25s; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
.btn-cta.primary { background: white; color: var(--blue-dark); border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
.btn-cta.primary:hover { transform: translateY(-2px); box-shadow: 0 14px 36px rgba(0,0,0,0.3); }
.btn-cta.secondary { background: transparent; color: white; border: 2px solid rgba(255,255,255,0.4); }
.btn-cta.secondary:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.7); transform: translateY(-2px); }

/* ======== FOOTER ======== */
footer {
  background: #060e1c;
  color: rgba(255,255,255,0.7);
  padding: 64px 40px 32px;
  border-top: 3px solid rgba(200,165,90,0.3);
}

.footer-inner { max-width: 1200px; margin: 0 auto; }

.footer-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 48px;
  padding-bottom: 48px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}

.footer-logo-row { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.footer-logo { width: 48px; object-fit: contain; filter: drop-shadow(0 2px 8px rgba(200,165,90,0.3)); }
.footer-brand-name { font-size: 22px; font-weight: 900; color: white; }
.footer-brand-desc { font-size: 13.5px; line-height: 1.7; color: rgba(255,255,255,0.5); font-weight: 500; margin-bottom: 20px; max-width: 280px; }
.footer-lgu-note { font-size: 12px; color: var(--gold); font-weight: 700; background: rgba(200,165,90,0.1); border: 1px solid rgba(200,165,90,0.2); border-radius: 8px; padding: 8px 12px; display: inline-block; }

.footer-col-title { font-size: 12px; font-weight: 800; color: var(--gold); letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 18px; }

.footer-links { list-style: none; display: flex; flex-direction: column; gap: 10px; }
.footer-links a { color: rgba(255,255,255,0.55); text-decoration: none; font-size: 13.5px; font-weight: 500; transition: color 0.2s; display: flex; align-items: center; gap: 8px; }
.footer-links a:hover { color: white; }
.footer-links a::before { content: '›'; color: var(--gold); font-weight: 700; }

.footer-bottom { display: flex; align-items: center; justify-content: space-between; padding-top: 28px; flex-wrap: wrap; gap: 12px; }
.footer-copy { font-size: 12.5px; color: rgba(255,255,255,0.35); font-weight: 500; }
.footer-gov { font-size: 12px; color: rgba(255,255,255,0.4); font-weight: 600; display: flex; align-items: center; gap: 8px; }
.footer-gov span { color: var(--gold); }

/* ======== MOBILE ======== */
.mobile-menu-btn { display: none; background: none; border: none; color: white; font-size: 22px; cursor: pointer; padding: 6px; }

@media (max-width: 1024px) {
  .hero-title { font-size: 44px; }
  .hero-inner { gap: 40px; }
  .features-grid { grid-template-columns: repeat(2, 1fr); }
  .services-grid { grid-template-columns: repeat(2, 1fr); }
  .footer-grid { grid-template-columns: 1fr 1fr; }
  .gallery-strip { grid-template-columns: repeat(2, 1fr); }
  .menro-section-inner { grid-template-columns: 1fr; gap: 32px; }
}

@media (max-width: 768px) {
  nav { padding: 0 20px; }
  .nav-links, .nav-actions .btn-nav-login.outline { display: none; }
  .mobile-menu-btn { display: block; }
  .hero-inner { grid-template-columns: 1fr; padding: 100px 24px 60px; text-align: center; }
  .hero-title { font-size: 38px; }
  .hero-subtitle { margin: 0 auto 32px; }
  .hero-actions { justify-content: center; }
  .hero-stats { justify-content: center; }
  .hero-visual { display: none; }
  .section { padding: 64px 24px; }
  .section-title { font-size: 30px; }
  .about-grid { grid-template-columns: 1fr; gap: 40px; }
  .hotlines-header { grid-template-columns: 1fr; gap: 40px; }
  .hotlines-grid { grid-template-columns: 1fr; }
  .features-grid { grid-template-columns: 1fr; }
  .services-grid { grid-template-columns: repeat(2, 1fr); }
  .news-grid { grid-template-columns: 1fr; }
  .gallery-strip { grid-template-columns: repeat(2, 1fr); }
  .lgu-banner { padding: 24px 20px; }
  .lgu-divider { display: none; }
  .lgu-banner-inner { flex-direction: column; text-align: center; }
  .footer-grid { grid-template-columns: 1fr; gap: 32px; }
  footer { padding: 48px 24px 28px; }
  .cta-section { padding: 64px 24px; }
  .mayor-section-inner { flex-direction: column; gap: 28px; text-align: center; }
  .menro-section { padding: 48px 24px; }
  .menro-visionmission { grid-template-columns: 1fr; }
}

/* ======== ANIMATIONS ======== */
.fade-up { opacity: 0; transform: translateY(30px); transition: opacity 0.6s ease, transform 0.6s ease; }
.fade-up.visible { opacity: 1; transform: translateY(0); }
</style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav>
  <a href="landing.php" class="nav-brand">
    <img src="https://malita.gov.ph/wp-content/uploads/2023/01/official_seal-min.png" alt="BiMAP Logo" class="nav-logo">
    <div class="nav-title">
      <span class="brand-name">BiMAP</span>
      <span class="brand-sub">Malita, Davao Occidental</span>
    </div>
  </a>

  <ul class="nav-links">
    <li><a href="#about">About</a></li>
    <li><a href="#features">Features</a></li>
    <li><a href="#services">Services</a></li>
    <li><a href="#news">News</a></li>
    <li><a href="#hotlines">Hotlines</a></li>
  </ul>

  <div class="nav-actions">
    <a href="index.php?login=1" class="btn-nav-login solid">
      <i class="fa-solid fa-lock"></i> Admin Login
    </a>
  </div>

  <button class="mobile-menu-btn" aria-label="Menu">
    <i class="fa-solid fa-bars"></i>
  </button>
</nav>

<!-- ===== HERO ===== -->
<section class="hero">
  <div class="hero-bg-pattern"></div>
  <div class="hero-glow-1"></div>
  <div class="hero-glow-2"></div>

  <div class="hero-inner">
    <div class="hero-content">
      <div class="hero-badge">
        <i class="fa-solid fa-shield-halved"></i>
        Official Platform of LGU Malita
      </div>

      <h1 class="hero-title">
        Serving the People
        <span class="highlight">of Malita,</span>
        Davao Occidental
      </h1>

      <p class="hero-subtitle">
        BiMAP — the Barangay Integrated Monitoring &amp; Alert Platform — connects residents, 
        barangay officials, and local government for faster, smarter community service delivery.
      </p>

      <div class="hero-actions">
        <a href="#about" class="btn-hero secondary">
          <i class="fa-solid fa-circle-info"></i> Learn More
        </a>
      </div>

      <div class="hero-stats">
        <div class="hero-stat">
          <div class="stat-num">50+</div>
          <div class="stat-label">Barangays</div>
        </div>
        <div class="hero-stat">
          <div class="stat-num">24/7</div>
          <div class="stat-label">Monitoring</div>
        </div>
        <div class="hero-stat">
          <div class="stat-num">1st</div>
          <div class="stat-label">Class Municipality</div>
        </div>
      </div>
    </div>

    <!-- Mayor Card in hero -->
    <div class="hero-visual">
      <div class="hero-glow-ring"></div>
      <div class="mayor-card">
        <img 
          src="https://malita.gov.ph/wp-content/uploads/2026/01/mayor-300x296.jpg" 
          alt="Hon. Mayor Benjamin P. Bautista Jr." 
          class="mayor-photo"
          onerror="this.src='https://malita.gov.ph/wp-content/uploads/2023/01/official_seal-min.png'; this.style.borderRadius='50%';"
        >
        <div class="mayor-card-name">Hon. Benjamin P. Bautista Jr.</div>
        <div class="mayor-card-title">Municipal Mayor of Malita</div>
        <div class="mayor-seal">
          <img src="https://malita.gov.ph/wp-content/uploads/2023/01/official_seal-min.png" alt="Malita Seal" style="width:40px;height:40px;object-fit:contain;border-radius:50%;" onerror="this.parentElement.innerHTML='🏛️';">
        </div>
        <div class="mayor-card-footer">Municipality of Malita · Davao Occidental</div>
      </div>
    </div>
  </div>
</section>

<!-- ===== LGU BANNER ===== -->
<div class="lgu-banner">
  <div class="lgu-banner-inner">
    <div class="lgu-logo-wrap">
      <img 
        src="https://malita.gov.ph/wp-content/uploads/2023/01/official_seal-min.png" 
        alt="LGU Malita Seal" 
        class="lgu-seal-img"
        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
      >
      <div style="display:none;width:68px;height:68px;border-radius:50%;background:rgba(200,165,90,0.15);border:2px solid var(--gold);align-items:center;justify-content:center;font-size:28px;">🏛️</div>
      <div class="lgu-text">
        <div class="lgu-label">Official Platform of</div>
        <div class="lgu-name">Local Government Unit of Malita</div>
        <div class="lgu-loc">📍 Barangay Poblacion, Malita, Davao Occidental 8012</div>
      </div>
    </div>
    <div class="lgu-divider"></div>
    <div class="lgu-tagline">
      "Connecting barangay communities to efficient, transparent, and responsive governance."
    </div>
  </div>
</div>

<!-- ===== ABOUT MALITA ===== -->
<section class="section" id="about">
  <div class="section-inner">
    <div class="about-grid">
      <div class="about-image-wrap fade-up">
        <div class="about-img-card">
          <img 
            src="https://malita.gov.ph/wp-content/uploads/2022/12/gaginaway.jpg" 
            alt="Gaginaway Festival — Malita, Davao Occidental"
            onerror="this.src='https://malita.gov.ph/wp-content/uploads/2023/11/406213301_310320175226464_4976625529918898121_n-300x225.jpg';"
          >
          <div class="about-img-overlay">
            <div class="overlay-title">🎉 Gaginaway Festival</div>
            <div class="overlay-sub">Celebrated every full moon of November · Malita Central Public Market</div>
          </div>
        </div>
        <div class="about-badge">
          <i class="fa-solid fa-star"></i>
          1st Class Municipality
        </div>
      </div>

      <div class="about-content fade-up">
        <div class="section-eyebrow">About Malita</div>
        <h2 class="section-title">A Thriving Community in Davao Occidental</h2>
        <p class="section-desc">
          Malita is the capital and first-class municipality of Davao Occidental, Philippines, 
          known for its rich cultural heritage, diverse indigenous communities, and vibrant 
          festivals. The municipality is celebrated for its arts, heritage, and four major 
          indigenous cultural communities — Tagakaulo, Blaan, Muslim, and Manobo groups.
        </p>

        <ul class="about-info-list">
          <li>
            <div class="icon"><i class="fa-solid fa-location-dot"></i></div>
            <div>
              <strong>Location & Classification</strong>
              1st class municipality and capital of Davao Occidental province. Home to diverse indigenous peoples and rich cultural arts.
            </div>
          </li>
          <li>
            <div class="icon"><i class="fa-solid fa-masks-theater"></i></div>
            <div>
              <strong>Gaginaway Festival</strong>
              Founded by Benjamin Joseph Bautista (Datu Alimbulungan). Celebrated every full moon in November by the four major IPs: Tagakaulo, Blaan, Muslim &amp; Manobo groups. Araw ng Malita is observed on November 17 annually.
            </div>
          </li>
          <li>
            <div class="icon"><i class="fa-solid fa-seedling"></i></div>
            <div>
              <strong>Agriculture & Livelihood</strong>
              Active programs through the Municipal Agriculturist's Office (0975 212 6926), including the National Soil Health Program (NSHP) for science-based farming.
            </div>
          </li>
          <li>
            <div class="icon"><i class="fa-solid fa-leaf"></i></div>
            <div>
              <strong>Environment & Natural Resources (MENRO)</strong>
              The MENRO is mandated under RA 7160 to conserve, preserve, and protect the environment and natural resources of the Municipality. Led by Roberto A. Daligdig, the office promotes ecological balance and environmental protection through community education and sustainable resource management. Contact: <a href="mailto:menro@malita.gov.ph" style="color:var(--blue);">menro@malita.gov.ph</a>
            </div>
          </li>
        </ul>

      </div>
    </div>
  </div>
</section>

<!-- ===== MAYOR SPOTLIGHT ===== -->
<div class="mayor-section">
  <div class="mayor-section-inner">
    <div class="mayor-photo-wrap fade-up">
      <img 
        src="https://malita.gov.ph/wp-content/uploads/2026/01/mayor-300x296.jpg"
        alt="Hon. Mayor Benjamin P. Bautista Jr."
        class="mayor-full-photo"
        onerror="this.style.display='none';"
      >
    </div>
    <div class="mayor-details fade-up">
      <div class="mayor-eyebrow">Message from the Mayor</div>
      <div class="mayor-name">Hon. Benjamin P. Bautista Jr.</div>
      <div class="mayor-pos">Municipal Mayor · Malita, Davao Occidental</div>
      <p class="mayor-desc">
        The Municipality of Malita is committed to delivering responsive, transparent, and 
        people-centered governance. Through BiMAP, we bring barangay services closer to every 
        resident — enabling faster complaint resolution, real-time community updates, and 
        improved coordination across all our barangays. Together, we build a stronger, more 
        connected Malita.
      </p>
    </div>
  </div>
</div>

<!-- ===== MENRO SPOTLIGHT ===== -->
<div class="menro-section" id="menro">
  <div class="menro-section-inner">
    <!-- Department Head -->
    <div class="fade-up" style="text-align:center;">
      <img
        src="https://malita.gov.ph/wp-content/uploads/2022/07/roberto-daligdig-min-150x150.png"
        alt="Roberto A. Daligdig"
        class="menro-head-photo"
        onerror="this.style.display='none';"
      >
      <div class="menro-head-name">Roberto A. Daligdig</div>
      <div class="menro-head-pos">Municipal Environment &amp; Natural Resources Officer</div>

      <div class="menro-hours" style="margin: 18px auto 0; width: fit-content;">
        <i class="fa-solid fa-clock"></i>
        Mon – Fri &nbsp;|&nbsp; 8:00 AM – 5:00 PM
      </div>

      <div style="margin-top: 20px;">
        <div style="font-size:11px;font-weight:800;color:#2e7d32;letter-spacing:1px;text-transform:uppercase;margin-bottom:10px;">Department Staff</div>
        <div class="menro-staff-grid" style="justify-content:center;">
          <div class="menro-staff-pill">
            <img src="https://malita.gov.ph/wp-content/uploads/2022/09/ian-senires-min.png" alt="Ian R. Señires" onerror="this.style.display='none';">
            <span><span>Ian R. Señires</span><span class="pill-role">Admin Assistant II</span></span>
          </div>
          <div class="menro-staff-pill">
            <img src="https://malita.gov.ph/wp-content/uploads/2022/09/patricio-abanid-jr-min.png" alt="Patricio B. Abanid Jr." onerror="this.style.display='none';">
            <span><span>Patricio B. Abanid Jr.</span><span class="pill-role">EMS II</span></span>
          </div>
          <div class="menro-staff-pill">
            <img src="https://malita.gov.ph/wp-content/uploads/2022/09/hannah-villaber-min.png" alt="Hannah Villaber" onerror="this.style.display='none';">
            <span><span>Hannah Villaber</span><span class="pill-role">Admin Aide II</span></span>
          </div>
          <div class="menro-staff-pill">
            <img src="https://malita.gov.ph/wp-content/uploads/2022/09/nestor-luna-jr-min.png" alt="Nestor E. Luna Jr." onerror="this.style.display='none';">
            <span><span>Nestor E. Luna Jr.</span><span class="pill-role">Forest Ranger</span></span>
          </div>
          <div class="menro-staff-pill">
            <img src="https://malita.gov.ph/wp-content/uploads/2022/09/nixon-john-mamolo-min.png" alt="Nixon John S. Mamolo" onerror="this.style.display='none';">
            <span><span>Nixon John S. Mamolo</span><span class="pill-role">Job Order</span></span>
          </div>
          <div class="menro-staff-pill">
            <img src="https://malita.gov.ph/wp-content/uploads/2022/09/jeharlene-maonio-min.png" alt="Jeharlene S. Maonio" onerror="this.style.display='none';">
            <span><span>Jeharlene S. Maonio</span><span class="pill-role">Job Order</span></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Vision, Mission, Mandate -->
    <div class="fade-up">
      <div class="menro-eyebrow">LGU Malita Office Spotlight</div>
      <div class="menro-content-title">Municipal Environment &amp; Natural Resources Office (MENRO)</div>
      <p style="font-size:14.5px;color:var(--muted);line-height:1.7;font-weight:500;">
        The MENRO takes charge of all environment and natural resource functions under 
        <strong>Section 17 of Republic Act 7160</strong> (Local Government Code of 1991), 
        serving as Malita's frontline office for environmental conservation, ecological 
        protection, and sustainable community development.
      </p>

      <div class="menro-visionmission">
        <div class="menro-vm-card">
          <h4>🌿 Vision</h4>
          <p>
            To have a clean and safe environment and be the model contributor in protecting 
            the environment and natural resources of the municipality.
          </p>
        </div>
        <div class="menro-vm-card">
          <h4>🎯 Mission</h4>
          <ul>
            <li>Sustain management, conserve, preserve, and protect the environment and natural resources of the Municipality.</li>
            <li>Create an environment-friendly and sustainable ecological balance.</li>
            <li>Encourage and educate individuals to maintain a lively, healthy community and participate in environmental protection programs.</li>
          </ul>
        </div>
      </div>

      <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:22px;align-items:center;">
        <a href="https://malita.gov.ph/wp-content/uploads/2022/07/MENRO-Citizens-Charter.pdf" target="_blank" class="btn-hero primary" style="font-size:13px;padding:10px 20px;">
          <i class="fa-solid fa-file-pdf"></i> Citizens Charter
        </a>
        <a href="https://malita.gov.ph/wp-content/uploads/2022/07/MENRO-Organizational-Chart.pdf" target="_blank" class="btn-hero secondary" style="font-size:13px;padding:10px 20px;background:rgba(46,125,50,0.1);border-color:rgba(46,125,50,0.4);color:#2e7d32;">
          <i class="fa-solid fa-sitemap"></i> Organizational Chart
        </a>
        <div style="font-size:13px;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:8px;">
          <i class="fa-solid fa-envelope" style="color:#2e7d32;"></i>
          <a href="mailto:menro@malita.gov.ph" style="color:var(--blue);text-decoration:none;">menro@malita.gov.ph</a>
        </div>
        <div style="font-size:13px;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:8px;">
          <i class="fa-solid fa-phone" style="color:#2e7d32;"></i> 0942 745 4941
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== FEATURES ===== -->
<section class="section features-section" id="features">
  <div class="section-inner">
    <div class="section-eyebrow fade-up">Platform Features</div>
    <h2 class="section-title fade-up">Everything Your Barangay Needs</h2>
    <p class="section-desc fade-up">BiMAP brings together complaint management, announcements, garbage collection monitoring, and real-time alerts in one unified platform for all Malita residents.</p>

    <div class="features-grid">
      <div class="feature-card fade-up">
        <div class="feature-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="feature-title">Complaint Reporting</div>
        <div class="feature-desc">Residents can file complaints directly from their phones. Barangay officials and admin receive, review, and resolve issues with full transparency.</div>
      </div>
      <div class="feature-card fade-up">
        <div class="feature-icon"><i class="fa-solid fa-bullhorn"></i></div>
        <div class="feature-title">LGU Announcements</div>
        <div class="feature-desc">Stay informed with official announcements from the LGU, targeted to residents, drivers, or the entire community of Malita.</div>
      </div>
      <div class="feature-card fade-up">
        <div class="feature-icon"><i class="fa-solid fa-truck"></i></div>
        <div class="feature-title">Garbage Collection Tracking</div>
        <div class="feature-desc">Monitor real-time status of garbage collection schedules. Drivers update routes while residents confirm collections easily.</div>
      </div>
      <div class="feature-card fade-up">
        <div class="feature-icon"><i class="fa-solid fa-location-crosshairs"></i></div>
        <div class="feature-title">Live Driver Location</div>
        <div class="feature-desc">Track collection vehicle locations in real-time, helping residents know exactly when their area will be served.</div>
      </div>
      <div class="feature-card fade-up">
        <div class="feature-icon"><i class="fa-solid fa-chart-column"></i></div>
        <div class="feature-title">Admin Dashboard</div>
        <div class="feature-desc">Comprehensive admin panel for LGU officials to monitor complaints, user activity, reports, and announcements across all barangays.</div>
      </div>
      <div class="feature-card fade-up">
        <div class="feature-icon"><i class="fa-solid fa-bell"></i></div>
        <div class="feature-title">Instant Notifications</div>
        <div class="feature-desc">Push notifications keep residents updated on complaint status changes, new announcements, and urgent community alerts.</div>
      </div>
    </div>
  </div>
</section>

<!-- ===== LGU SERVICES ===== -->
<section class="section" id="services">
  <div class="section-inner">
    <div class="section-eyebrow fade-up">Government Services</div>
    <h2 class="section-title fade-up">LGU Malita Service Offices</h2>
    <p class="section-desc fade-up">Access key government services from the Municipality of Malita, now integrated through the BiMAP platform.</p>

    <div class="services-grid">
      <div class="service-card fade-up">
        <div class="service-icon-wrap"><i class="fa-solid fa-file-contract"></i></div>
        <div class="service-title">Civil Registry</div>
        <div class="service-desc">Birth, death, and marriage certificates through the Municipal Civil Registrar's Office.</div>
        <div class="service-phone"><i class="fa-solid fa-phone fa-xs"></i> 0948 645 4011</div>
      </div>
      <div class="service-card fade-up">
        <div class="service-icon-wrap"><i class="fa-solid fa-heart-pulse"></i></div>
        <div class="service-title">Health & Welfare</div>
        <div class="service-desc">Medical services and social welfare programs from the Municipal Health Office and MSWDO.</div>
        <div class="service-phone"><i class="fa-solid fa-phone fa-xs"></i> MSWDO: 0946 252 4476</div>
      </div>
      <div class="service-card fade-up">
        <div class="service-icon-wrap"><i class="fa-solid fa-store"></i></div>
        <div class="service-title">Business Permits (BOSS)</div>
        <div class="service-desc">Business registration and licensing through the Business Permit &amp; Licensing Office.</div>
        <div class="service-phone"><i class="fa-solid fa-phone fa-xs"></i> 082 272 8497</div>
      </div>
      <div class="service-card fade-up">
        <div class="service-icon-wrap"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="service-title">Peace & Order / DRRM</div>
        <div class="service-desc">Community safety, disaster risk reduction managed by the MDRRMO.</div>
        <div class="service-phone"><i class="fa-solid fa-phone fa-xs"></i> 0946 635 3208</div>
      </div>
      <div class="service-card fade-up">
        <div class="service-icon-wrap"><i class="fa-solid fa-tractor"></i></div>
        <div class="service-title">Agriculture (NSHP)</div>
        <div class="service-desc">Soil health programs, crop support, and science-based farming through the Municipal Agriculturist's Office.</div>
        <div class="service-phone"><i class="fa-solid fa-phone fa-xs"></i> 0975 212 6926</div>
      </div>
      <div class="service-card fade-up">
        <div class="service-icon-wrap"><i class="fa-solid fa-hard-hat"></i></div>
        <div class="service-title">Engineering Office</div>
        <div class="service-desc">Infrastructure, public works, and construction permits through the Municipal Engineer's Office.</div>
        <div class="service-phone"><i class="fa-solid fa-phone fa-xs"></i> 0917 136 6021</div>
      </div>
      <!-- UPDATED: MENRO card with accurate info -->
      <div class="service-card fade-up">
        <div class="service-icon-wrap"><i class="fa-solid fa-leaf"></i></div>
        <div class="service-title">Environment &amp; Natural Resources (MENRO)</div>
        <div class="service-desc">Environmental conservation, natural resource management, and ecological protection under RA 7160. Led by Roberto A. Daligdig. Open Mon–Fri, 8 AM–5 PM.</div>
        <div class="service-phone"><i class="fa-solid fa-envelope fa-xs"></i> menro@malita.gov.ph</div>
        <div class="service-phone"><i class="fa-solid fa-phone fa-xs"></i> 0942 745 4941</div>
      </div>
      <div class="service-card fade-up">
        <div class="service-icon-wrap"><i class="fa-solid fa-calculator"></i></div>
        <div class="service-title">Treasury & Budget</div>
        <div class="service-desc">Tax collection, financial services, and budget administration from the Municipal Treasurer's Office.</div>
        <div class="service-phone"><i class="fa-solid fa-phone fa-xs"></i> 0946 920 6895</div>
      </div>
    </div>
  </div>
</section>

<!-- ===== NEWS & ANNOUNCEMENTS ===== -->
<section class="section news-section" id="news">
  <div class="section-inner">
    <div class="section-eyebrow fade-up">News & Announcements</div>
    <h2 class="section-title fade-up">Latest from LGU Malita</h2>
    <p class="section-desc fade-up">Stay up to date with official news, government programs, and community events from the Municipality of Malita.</p>

    <div class="news-grid fade-up">
      <div class="news-featured">
        <div class="news-featured-img">
          <img 
            src="https://malita.gov.ph/wp-content/uploads/2023/11/406213301_310320175226464_4976625529918898121_n-300x225.jpg"
            alt="16th Gaginaway Festival"
            onerror="this.parentElement.style.background='linear-gradient(135deg,#0d47a1,#1a73e8)'; this.parentElement.style.display='flex'; this.parentElement.style.alignItems='center'; this.parentElement.style.justifyContent='center'; this.parentElement.style.fontSize='64px'; this.outerHTML='📢';"
          >
        </div>
        <div class="news-featured-body">
          <span class="news-tag">Agriculture</span>
          <div class="news-title">Mobile Soil Laboratory (MSL) Exit Conference in Davao Occidental</div>
          <div class="news-excerpt">
            The successful conduct of the MSL Exit Conference marks a meaningful step forward in strengthening 
            soil health management and science-based agriculture in our province. Updated soil analysis results 
            and soil health maps were formally turned over to LGUs, agricultural technicians, and farmers through 
            the National Soil Health Program (NSHP).
          </div>
          <div class="news-meta">
            <span><i class="fa-regular fa-calendar"></i> January 30, 2026</span>
            <span><i class="fa-solid fa-tag"></i> Community</span>
          </div>
        </div>
      </div>

      <div class="news-list">
        <div class="news-item">
          <div class="news-item-tag">📣 Community Notice</div>
          <div class="news-item-title">Notice to the Public — Lost Official Receipts (AF No. 51) — Municipal Treasurer's Office</div>
          <div class="news-item-date"><i class="fa-regular fa-calendar"></i> May 4, 2026</div>
        </div>
        <div class="news-item">
          <div class="news-item-tag">🏅 Sports</div>
          <div class="news-item-title">Davao Occidental Provincial Athletic Association Meet 2026</div>
          <div class="news-item-date"><i class="fa-regular fa-calendar"></i> January 29, 2026</div>
        </div>
        <div class="news-item">
          <div class="news-item-tag">🏪 Business</div>
          <div class="news-item-title">Business One Stop Shop (BOSS) 2026 — Registration Period Open</div>
          <div class="news-item-date"><i class="fa-regular fa-calendar"></i> January 26, 2026</div>
        </div>
        <div class="news-item">
          <div class="news-item-tag">🏆 Awards</div>
          <div class="news-item-title">Call for Applications — 2026 Presidential Filipinnovation Awards (PFA)</div>
          <div class="news-item-date"><i class="fa-regular fa-calendar"></i> February 2026</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== GALLERY STRIP ===== -->
<section class="section gallery-section" id="gallery">
  <div class="section-inner">
    <div class="section-eyebrow fade-up">Photo Gallery</div>
    <h2 class="section-title fade-up">Life in Malita</h2>
    <p class="section-desc fade-up">Glimpses of Malita's vibrant culture, festivals, government programs, and community events.</p>

    <div class="gallery-strip fade-up">
      <div class="gallery-item">
        <img src="https://malita.gov.ph/wp-content/uploads/2022/12/gaginaway.jpg" alt="15th Gaginaway Festival">
        <div class="gallery-overlay"><i class="fa-solid fa-expand"></i></div>
        <div class="gallery-label">Gaginaway Festival</div>
      </div>
      <div class="gallery-item">
        <img src="https://malita.gov.ph/wp-content/uploads/2022/12/performance-min.jpg" alt="Cultural Dance">
        <div class="gallery-overlay"><i class="fa-solid fa-expand"></i></div>
        <div class="gallery-label">Cultural Dance</div>
      </div>
      <div class="gallery-item">
        <img src="https://malita.gov.ph/wp-content/uploads/2022/12/torch_parade-min.jpg" alt="Torch Parade">
        <div class="gallery-overlay"><i class="fa-solid fa-expand"></i></div>
        <div class="gallery-label">Torch Parade</div>
      </div>
      <div class="gallery-item">
        <img src="https://malita.gov.ph/wp-content/uploads/2022/12/awarding-min.jpg" alt="Awarding Ceremony">
        <div class="gallery-overlay"><i class="fa-solid fa-expand"></i></div>
        <div class="gallery-label">Awarding Ceremony</div>
      </div>
    </div>
  </div>
</section>

<!-- ===== HOTLINES ===== -->
<section class="section" id="hotlines">
  <div class="section-inner">
    <div class="hotlines-header">
      <div>
        <div class="section-eyebrow fade-up">Emergency Contacts</div>
        <h2 class="section-title fade-up">Emergency & Office Hotlines</h2>
        <p class="section-desc fade-up">
          In case of emergency, contact the appropriate Malita LGU offices immediately. 
          BiMAP also lets you report incidents directly from your phone.
        </p>

        <div class="hotlines-grid fade-up">
          <div class="hotline-card">
            <div class="hc-icon red"><i class="fa-solid fa-fire"></i></div>
            <div class="hc-body">
              <div class="hc-label">Emergency</div>
              <div class="hc-name">Bureau of Fire</div>
              <div class="hc-num">164 / Local BFP</div>
            </div>
          </div>
          <div class="hotline-card">
            <div class="hc-icon blue"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="hc-body">
              <div class="hc-label">Security</div>
              <div class="hc-name">Philippine National Police</div>
              <div class="hc-num">166 / Local PNP</div>
            </div>
          </div>
          <div class="hotline-card">
            <div class="hc-icon orange"><i class="fa-solid fa-house-flood-water"></i></div>
            <div class="hc-body">
              <div class="hc-label">Disaster</div>
              <div class="hc-name">MDRRMO</div>
              <div class="hc-num">0946 635 3208</div>
            </div>
          </div>
          <div class="hotline-card">
            <div class="hc-icon green"><i class="fa-solid fa-truck-medical"></i></div>
            <div class="hc-body">
              <div class="hc-label">Social Welfare</div>
              <div class="hc-name">MSWDO Office</div>
              <div class="hc-num">0946 252 4476</div>
            </div>
          </div>
          <div class="hotline-card">
            <div class="hc-icon purple"><i class="fa-solid fa-person-digging"></i></div>
            <div class="hc-body">
              <div class="hc-label">Infrastructure</div>
              <div class="hc-name">Engineering Office</div>
              <div class="hc-num">0917 136 6021</div>
            </div>
          </div>
          <!-- UPDATED: MENRO hotline card with accurate info -->
          <div class="hotline-card">
            <div class="hc-icon teal"><i class="fa-solid fa-leaf"></i></div>
            <div class="hc-body">
              <div class="hc-label">Environment</div>
              <div class="hc-name">MENRO Office</div>
              <div class="hc-num">0942 745 4941</div>
              <div style="font-size:11px;color:var(--muted);font-weight:600;margin-top:2px;">menro@malita.gov.ph</div>
            </div>
          </div>
        </div>
      </div>

      <div class="hotlines-cta-wrap fade-up">
        <!-- Real emergency hotline image from the site -->
        <div class="emergency-img-wrap">
          <img 
            src="https://malita.gov.ph/wp-content/uploads/2026/01/emergency_hotline.jpg"
            alt="LGU Malita Emergency Hotline Numbers"
            onerror="this.parentElement.style.display='none';"
          >
        </div>

        <div class="contact-block">
          <h3>📍 Municipal Hall of Malita</h3>
          <p>Barangay Poblacion, Malita, Davao Occidental 8012</p>
          <div class="contact-detail">
            <i class="fa-solid fa-globe"></i>
            malita.gov.ph
          </div>
          <div class="contact-detail">
            <i class="fa-solid fa-envelope"></i>
            info@malita.gov.ph
          </div>
          <div class="contact-detail">
            <i class="fa-solid fa-phone"></i>
            Vice Mayor's Office: 0938 919 3607
          </div>
          <div class="contact-detail">
            <i class="fa-solid fa-phone"></i>
            Admin Office: 0916 116 1786
          </div>
          <div class="contact-detail">
            <i class="fa-solid fa-leaf"></i>
            MENRO: 0942 745 4941
          </div>
        </div>

        <div style="background: #f4f7fc; border-radius: 20px; padding: 28px; border: 1.5px solid var(--border); text-align: center;">
          <div style="font-size: 36px; margin-bottom: 12px;">📱</div>
          <div style="font-size: 16px; font-weight: 800; color: var(--text); margin-bottom: 8px;">Report via BiMAP</div>
          <div style="font-size: 13px; color: var(--muted); font-weight: 500; margin-bottom: 18px; line-height: 1.6;">Submit community complaints, incidents, or garbage collection feedback directly through the app.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer>
  <div class="footer-inner">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="footer-logo-row">
          <img src="https://malita.gov.ph/wp-content/uploads/2023/01/official_seal-min.png" alt="BiMAP / LGU Malita" class="footer-logo">
          <span class="footer-brand-name">BiMAP</span>
        </div>
        <div class="footer-brand-desc">
          Barangay Integrated Monitoring &amp; Alert Platform — the official digital service platform 
          of the Local Government Unit of Malita, Davao Occidental.
        </div>
        <div class="footer-lgu-note">🏛️ Official LGU Malita Platform</div>
      </div>

      <div>
        <div class="footer-col-title">Quick Links</div>
        <ul class="footer-links">
          <li><a href="#about">About Malita</a></li>
          <li><a href="#features">BiMAP Features</a></li>
          <li><a href="#services">LGU Services</a></li>
          <li><a href="#news">News & Updates</a></li>
          <li><a href="#hotlines">Emergency Hotlines</a></li>
        </ul>
      </div>

      <div>
        <div class="footer-col-title">BiMAP Access</div>
        <ul class="footer-links">
          <li><a href="index.php?login=1">Admin Panel</a></li>
        </ul>
      </div>

      <div>
        <div class="footer-col-title">LGU Malita</div>
        <ul class="footer-links">
          <li><a href="https://malita.gov.ph" target="_blank">Official Website</a></li>
          <li><a href="https://malita.gov.ph/news/" target="_blank">News & Updates</a></li>
          <li><a href="https://malita.gov.ph/public-announcements/" target="_blank">Announcements</a></li>
          <li><a href="https://malita.gov.ph/citizens-charter/" target="_blank">Citizen's Charter</a></li>
          <li><a href="https://malita.gov.ph/directory/municipal-environment-and-natural-resources-office/" target="_blank">MENRO Office</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="footer-copy">
        © 2026 BiMAP — Barangay Integrated Monitoring & Alert Platform. Municipality of Malita, Davao Occidental. All rights reserved.
      </div>
      <div class="footer-gov">
        <span>🇵🇭</span> Republic of the Philippines &nbsp;|&nbsp; <span>malita.gov.ph</span>
      </div>
    </div>
  </div>
</footer>

<script>
const fadeEls = document.querySelectorAll('.fade-up');
const observer = new IntersectionObserver((entries) => {
  entries.forEach(el => {
    if (el.isIntersecting) el.target.classList.add('visible');
  });
}, { threshold: 0.12 });
fadeEls.forEach(el => observer.observe(el));

document.querySelectorAll('a[href^="#"]').forEach(link => {
  link.addEventListener('click', (e) => {
    const target = document.querySelector(link.getAttribute('href'));
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});

const mobileBtn = document.querySelector('.mobile-menu-btn');
mobileBtn.addEventListener('click', () => {
  const links = document.querySelector('.nav-links');
  if (links.style.display === 'flex') {
    links.style.display = '';
  } else {
    links.style.cssText = 'display:flex;flex-direction:column;position:absolute;top:68px;left:0;right:0;background:rgba(13,28,58,0.98);padding:20px 24px;gap:16px;z-index:999;';
  }
});
</script>
</body>
</html>