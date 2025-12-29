import { Injectable, UnauthorizedException, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import * as admin from 'firebase-admin';
import { CurrentUserData } from '../common/decorators/current-user.decorator';

@Injectable()
export class FirebaseStrategy {
  private readonly logger = new Logger(FirebaseStrategy.name);
  private firebaseApp: admin.app.App | null = null;

  constructor(private configService: ConfigService) {
    this.initializeFirebase();
  }

  private initializeFirebase() {
    // Check if Firebase credentials are configured
    const projectId = this.configService.get<string>('FIREBASE_PROJECT_ID');
    const privateKey = this.configService.get<string>('FIREBASE_PRIVATE_KEY');
    const clientEmail = this.configService.get<string>('FIREBASE_CLIENT_EMAIL');

    if (!projectId || !privateKey || !clientEmail) {
      this.logger.warn(
        'Firebase credentials not configured. Authentication will not work. ' +
        'Please set FIREBASE_PROJECT_ID, FIREBASE_PRIVATE_KEY, and FIREBASE_CLIENT_EMAIL in your .env file.',
      );
      return;
    }

    try {
      if (!admin.apps.length) {
        this.firebaseApp = admin.initializeApp({
          credential: admin.credential.cert({
            projectId,
            privateKey: privateKey.replace(/\\n/g, '\n'),
            clientEmail,
          }),
        });
        this.logger.log('Firebase Admin SDK initialized successfully');
      } else {
        this.firebaseApp = admin.app();
      }
    } catch (error) {
      this.logger.error('Failed to initialize Firebase Admin SDK', error);
      throw new Error(
        `Firebase initialization failed: ${error instanceof Error ? error.message : 'Unknown error'}`,
      );
    }
  }

  async validateToken(token: string): Promise<CurrentUserData> {
    if (!this.firebaseApp) {
      throw new UnauthorizedException(
        'Firebase is not configured. Please configure Firebase credentials.',
      );
    }

    try {
      const decodedToken = await this.firebaseApp.auth().verifyIdToken(token);
      
      return {
        id: decodedToken.uid, // Will be mapped to database user ID
        firebaseUid: decodedToken.uid,
        email: decodedToken.email || '',
        displayName: decodedToken.name || undefined,
      };
    } catch (error) {
      this.logger.debug('Token validation failed', error);
      throw new UnauthorizedException('Invalid or expired token');
    }
  }
}

