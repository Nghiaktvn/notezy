import React from 'react';
import Navbar from '../components/landing/Navbar.jsx';
import Hero from '../components/landing/Hero.jsx';
import Features from '../components/landing/Features.jsx';
import HowItWorks from '../components/landing/HowItWorks.jsx';
import VideoDemo from '../components/landing/VideoDemo.jsx';
import CTASection from '../components/landing/CTASection.jsx';
import Footer from '../components/landing/Footer.jsx';

export default function Landing() {
  return (
    <div className="min-h-screen bg-white text-gray-900 antialiased transition-colors duration-300 dark:bg-black dark:text-gray-100">
      <Navbar />
      <main>
        <Hero />
        <Features />
        <HowItWorks />
        <VideoDemo />
        <CTASection />
      </main>
      <Footer />
    </div>
  );
}
