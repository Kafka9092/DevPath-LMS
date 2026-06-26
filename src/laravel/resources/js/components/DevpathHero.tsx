import React from 'react';
import { motion } from 'framer-motion';
import { router } from '@inertiajs/react';

const DevPathHero: React.FC = () => {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.15,
        delayChildren: 0.2,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 30 },
    visible: {
      opacity: 1,
      y: 0,
      transition: {
        duration: 0.6,
        ease: [0.22, 1, 0.36, 1],
      },
    },
  };

  const buttonVariants = {
    hidden: { opacity: 0, y: 30 },
    visible: {
      opacity: 1,
      y: 0,
      transition: {
        duration: 0.6,
        ease: [0.22, 1, 0.36, 1],
      },
    },
    hover: {
      scale: 1.05,
      transition: { duration: 0.2, ease: 'easeOut' },
    },
    tap: { scale: 0.95 },
  };

  return (
   <div className="flex items-center justify-center px-4 h-screen">
      <motion.div
        className="text-center max-w-3xl flex flex-col items-center gap-4"
        variants={containerVariants}
        initial="hidden"
        animate="visible"
      >
        <motion.h1
          variants={itemVariants}
          className="text-6xl sm:text-7xl md:text-8xl lg:text-9xl font-extrabold tracking-tight leading-none"
        >
          <span className="text-slate-950">Dev</span>
          <span className="text-violet-700">Path</span>
        </motion.h1>

        <motion.p
          variants={itemVariants}
          className="text-base md:text-lg text-slate-600 font-normal leading-relaxed mt-1"
        >
          Персонализированное обучение<br className="hidden md:block" />
          программированию с ИИ
        </motion.p>

        <motion.button
          variants={buttonVariants}
          whileHover="hover"
          whileTap="tap"
          onClick={() => router.visit('/login')}
          className="mt-4 bg-violet-700 text-white px-12 py-3.5 rounded-2xl text-sm font-bold tracking-wide min-w-[220px] shadow-lg shadow-violet-300/40 hover:bg-orange-500 cursor-pointer"
        >
          Начать обучение
        </motion.button>
      </motion.div>
    </div>
  );
};

export default DevPathHero;
