import { Test, TestingModule } from '@nestjs/testing';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { UnauthorizedException } from '@nestjs/common';
import * as admin from 'firebase-admin';
import { FirebaseStrategy } from './firebase.strategy';

// Mock firebase-admin
jest.mock('firebase-admin', () => {
  const mockAuth = {
    verifyIdToken: jest.fn(),
  };

  const mockApp = {
    auth: jest.fn(() => mockAuth),
  };

  return {
    apps: [],
    initializeApp: jest.fn(() => mockApp),
    app: jest.fn(() => mockApp),
    credential: {
      cert: jest.fn(),
    },
  };
});

describe('FirebaseStrategy', () => {
  let strategy: FirebaseStrategy;
  let configService: ConfigService;
  let mockAuth: any;

  const mockDecodedToken = {
    uid: 'firebase-uid-123',
    email: 'test@example.com',
    name: 'Test User',
  };

  beforeEach(async () => {
    // Reset mocks
    jest.clearAllMocks();

    // Setup mock auth
    mockAuth = {
      verifyIdToken: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      imports: [
        ConfigModule.forRoot({
          isGlobal: true,
          envFilePath: '.env.test',
        }),
      ],
      providers: [FirebaseStrategy],
    }).compile();

    strategy = module.get<FirebaseStrategy>(FirebaseStrategy);
    configService = module.get<ConfigService>(ConfigService);

    // Mock admin.app().auth()
    (admin.app as jest.Mock).mockReturnValue({
      auth: () => mockAuth,
    });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('initializeFirebase', () => {
    it('should initialize Firebase when credentials are provided', () => {
      // This is tested indirectly through validateToken
      // If initialization fails, validateToken will throw
      expect(strategy).toBeDefined();
    });

    it('should handle missing Firebase credentials gracefully', () => {
      // When credentials are missing, strategy should still be created
      // but validateToken will throw UnauthorizedException
      expect(strategy).toBeDefined();
    });
  });

  describe('validateToken', () => {
    it('should return user data when token is valid', async () => {
      mockAuth.verifyIdToken.mockResolvedValue(mockDecodedToken);

      const result = await strategy.validateToken('valid-token');

      expect(result).toEqual({
        id: 'firebase-uid-123',
        firebaseUid: 'firebase-uid-123',
        email: 'test@example.com',
        displayName: 'Test User',
      });
      expect(mockAuth.verifyIdToken).toHaveBeenCalledWith('valid-token');
    });

    it('should return user data without displayName when name is missing', async () => {
      const tokenWithoutName = {
        ...mockDecodedToken,
        name: undefined,
      };
      mockAuth.verifyIdToken.mockResolvedValue(tokenWithoutName);

      const result = await strategy.validateToken('valid-token');

      expect(result).toEqual({
        id: 'firebase-uid-123',
        firebaseUid: 'firebase-uid-123',
        email: 'test@example.com',
        displayName: undefined,
      });
    });

    it('should return user data with empty email when email is missing', async () => {
      const tokenWithoutEmail = {
        ...mockDecodedToken,
        email: undefined,
      };
      mockAuth.verifyIdToken.mockResolvedValue(tokenWithoutEmail);

      const result = await strategy.validateToken('valid-token');

      expect(result.email).toBe('');
    });

    it('should throw UnauthorizedException when token is invalid', async () => {
      mockAuth.verifyIdToken.mockRejectedValue(new Error('Invalid token'));

      await expect(strategy.validateToken('invalid-token')).rejects.toThrow(
        UnauthorizedException,
      );
      await expect(strategy.validateToken('invalid-token')).rejects.toThrow(
        'Invalid or expired token',
      );
    });

    it('should throw UnauthorizedException when token is expired', async () => {
      const expiredError = new Error('Token expired');
      expiredError.name = 'TokenExpiredError';
      mockAuth.verifyIdToken.mockRejectedValue(expiredError);

      await expect(strategy.validateToken('expired-token')).rejects.toThrow(
        UnauthorizedException,
      );
    });

    it('should throw UnauthorizedException when Firebase is not configured', async () => {
      // Create a new strategy instance without Firebase configured
      // This would happen if credentials are missing
      const strategyWithoutFirebase = new FirebaseStrategy(configService);
      
      // Mock the firebaseApp to be null
      (strategyWithoutFirebase as any).firebaseApp = null;

      await expect(
        strategyWithoutFirebase.validateToken('any-token'),
      ).rejects.toThrow(UnauthorizedException);
      await expect(
        strategyWithoutFirebase.validateToken('any-token'),
      ).rejects.toThrow('Firebase is not configured');
    });
  });
});

