import React from 'react';
import Navbar from '../components/landing/Navbar.jsx';
import VideoBanner from '../components/landing/VideoBanner.jsx';
import Hero from '../components/landing/Hero.jsx';
import StudyWorkspace from '../components/landing/StudyWorkspace.jsx';
import StudyGallery from '../components/landing/StudyGallery.jsx';
import Features from '../components/landing/Features.jsx';
import HowItWorks from '../components/landing/HowItWorks.jsx';
import CTASection from '../components/landing/CTASection.jsx';
import AboutContact from '../components/landing/AboutContact.jsx';
import AiTeaser from '../components/landing/AiTeaser.jsx';
import Footer from '../components/landing/Footer.jsx';

export default function Landing() {
  return (
    <div id="top" className="notezy-marketing min-h-screen antialiased">
      <Navbar />
      <main>
        <VideoBanner />
        <Hero />
        <StudyWorkspace />
        <StudyGallery />
        <Features />
        <HowItWorks />
        <CTASection />
        <AboutContact />
      </main>
      <Footer />
      <AiTeaser />
    </div>
  );
}
