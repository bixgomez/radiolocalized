#!/usr/bin/env node

import { execSync } from 'child_process'

console.log('Building with Vite...\n')

try {
  execSync('npx vite build', { stdio: 'inherit' })
  console.log('\nBuild complete!\n')
} catch (error) {
  console.error('Build failed:', error.message)
  process.exit(1)
}
