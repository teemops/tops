import * as crypto from 'crypto';

export class EncryptionUtil {
  private static readonly ALGORITHM = 'aes-256-gcm';
  private static readonly IV_LENGTH = 16;
  private static readonly SALT_LENGTH = 64;
  private static readonly TAG_LENGTH = 16;
  private static readonly TAG_POSITION = this.SALT_LENGTH + this.IV_LENGTH;
  private static readonly ENCRYPTED_POSITION = this.TAG_POSITION + this.TAG_LENGTH;

  /**
   * Encrypts a string using AES-256-GCM
   */
  static encrypt(text: string, key: string): string {
    const iv = crypto.randomBytes(this.IV_LENGTH);
    const salt = crypto.randomBytes(this.SALT_LENGTH);

    const keyBuffer = crypto.scryptSync(key, salt, 32);
    const cipher = crypto.createCipheriv(this.ALGORITHM, keyBuffer, iv);

    let encrypted = cipher.update(text, 'utf8', 'hex');
    encrypted += cipher.final('hex');

    const tag = cipher.getAuthTag();

    return Buffer.concat([
      salt,
      iv,
      tag,
      Buffer.from(encrypted, 'hex'),
    ]).toString('base64');
  }

  /**
   * Decrypts a string encrypted with encrypt()
   */
  static decrypt(encryptedData: string, key: string): string {
    const data = Buffer.from(encryptedData, 'base64');

    const salt = data.subarray(0, this.SALT_LENGTH);
    const iv = data.subarray(this.SALT_LENGTH, this.SALT_LENGTH + this.IV_LENGTH);
    const tag = data.subarray(this.TAG_POSITION, this.TAG_POSITION + this.TAG_LENGTH);
    const encrypted = data.subarray(this.ENCRYPTED_POSITION);

    const keyBuffer = crypto.scryptSync(key, salt, 32);
    const decipher = crypto.createDecipheriv(this.ALGORITHM, keyBuffer, iv);
    decipher.setAuthTag(tag);

    let decrypted = decipher.update(encrypted, undefined, 'utf8');
    decrypted += decipher.final('utf8');

    return decrypted;
  }
}

