import readline from 'readline';
import { db } from '../db/index.js';
import { config } from '../config/index.js';
import { hashPassword } from '../utils/crypto.js';

async function resetAdminPassword() {
  console.log('====================================================');
  console.log('USS Incident Report System - Super Admin Password Reset');
  console.log('====================================================');

  await db.init();

  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
  });

  const question = (prompt: string): Promise<string> => {
    return new Promise((resolve) => rl.question(prompt, resolve));
  };

  try {
    const usernameInput = await question(
      `Enter Super Admin username [default: ${config.superadmin.username}]: `
    );
    const username = usernameInput.trim() || config.superadmin.username;

    const admin = await db.getAdminByUsername(username);
    if (!admin) {
      console.error(`Error: Super Admin user "${username}" does not exist in the database.`);
      rl.close();
      process.exit(1);
    }

    const newPass = await question('Enter new password (min 8 chars): ');
    if (!newPass || newPass.trim().length < 8) {
      console.error('Error: Password must be at least 8 characters long.');
      rl.close();
      process.exit(1);
    }

    const hashed = await hashPassword(newPass.trim());
    admin.password_hash = hashed;
    admin.must_change_password = true; // force change on next login
    admin.updated_at = new Date().toISOString();

    await db.saveAdmin(admin);

    console.log(`\nSuccess! Super Admin "${username}" password has been reset.`);
    console.log('Next login will require updating password.');
  } finally {
    rl.close();
  }
}

resetAdminPassword()
  .then(() => process.exit(0))
  .catch((err) => {
    console.error('Failed to reset password:', err);
    process.exit(1);
  });
