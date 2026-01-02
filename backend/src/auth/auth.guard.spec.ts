import { Test, TestingModule } from '@nestjs/testing';
import { ExecutionContext, UnauthorizedException } from '@nestjs/common';
import { AuthGuard } from './auth.guard';
import { FirebaseStrategy } from './firebase.strategy';
import { PrismaService } from '../prisma/prisma.service';
import { CurrentUserData } from '../common/decorators/current-user.decorator';

describe('AuthGuard', () => {
  let guard: AuthGuard;
  let firebaseStrategy: FirebaseStrategy;
  let prismaService: PrismaService;

  const mockFirebaseStrategy = {
    validateToken: jest.fn(),
  };

  const mockPrismaService = {
    user: {
      upsert: jest.fn(),
    },
  };

  const mockExecutionContext = {
    switchToHttp: jest.fn(() => ({
      getRequest: jest.fn(),
    })),
  } as unknown as ExecutionContext;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        AuthGuard,
        {
          provide: FirebaseStrategy,
          useValue: mockFirebaseStrategy,
        },
        {
          provide: PrismaService,
          useValue: mockPrismaService,
        },
      ],
    }).compile();

    guard = module.get<AuthGuard>(AuthGuard);
    firebaseStrategy = module.get<FirebaseStrategy>(FirebaseStrategy);
    prismaService = module.get<PrismaService>(PrismaService);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('canActivate', () => {
    const mockRequest = {
      headers: {},
      user: undefined,
    };

    const mockFirebaseUser: CurrentUserData = {
      id: 'firebase-uid-123',
      firebaseUid: 'firebase-uid-123',
      email: 'test@example.com',
      displayName: 'Test User',
    };

    const mockDbUser = {
      id: 'db-user-id-123',
      firebaseUid: 'firebase-uid-123',
      email: 'test@example.com',
      displayName: 'Test User',
      defaultOrgId: null,
      createdAt: new Date(),
      updatedAt: new Date(),
    };

    beforeEach(() => {
      (mockExecutionContext.switchToHttp().getRequest as jest.Mock).mockReturnValue(
        mockRequest,
      );
    });

    it('should allow request with valid Bearer token', async () => {
      mockRequest.headers.authorization = 'Bearer valid-token';
      mockFirebaseStrategy.validateToken.mockResolvedValue(mockFirebaseUser);
      mockPrismaService.user.upsert.mockResolvedValue(mockDbUser);

      const result = await guard.canActivate(mockExecutionContext);

      expect(result).toBe(true);
      expect(mockFirebaseStrategy.validateToken).toHaveBeenCalledWith('valid-token');
      expect(mockPrismaService.user.upsert).toHaveBeenCalled();
      expect(mockRequest.user).toEqual({
        id: 'db-user-id-123',
        firebaseUid: 'firebase-uid-123',
        email: 'test@example.com',
        displayName: 'Test User',
        defaultOrgId: null,
      });
    });

    it('should throw UnauthorizedException when authorization header is missing', async () => {
      mockRequest.headers.authorization = undefined;

      await expect(guard.canActivate(mockExecutionContext)).rejects.toThrow(
        UnauthorizedException,
      );
      await expect(guard.canActivate(mockExecutionContext)).rejects.toThrow(
        'Missing or invalid authorization header',
      );
      expect(mockFirebaseStrategy.validateToken).not.toHaveBeenCalled();
    });

    it('should throw UnauthorizedException when authorization header is empty', async () => {
      mockRequest.headers.authorization = '';

      await expect(guard.canActivate(mockExecutionContext)).rejects.toThrow(
        UnauthorizedException,
      );
      expect(mockFirebaseStrategy.validateToken).not.toHaveBeenCalled();
    });

    it('should throw UnauthorizedException when authorization header does not start with Bearer', async () => {
      mockRequest.headers.authorization = 'Invalid token';

      await expect(guard.canActivate(mockExecutionContext)).rejects.toThrow(
        UnauthorizedException,
      );
      expect(mockFirebaseStrategy.validateToken).not.toHaveBeenCalled();
    });

    it('should throw UnauthorizedException when authorization header has Bearer but no token', async () => {
      mockRequest.headers.authorization = 'Bearer ';

      await expect(guard.canActivate(mockExecutionContext)).rejects.toThrow(
        UnauthorizedException,
      );
    });

    it('should create user in database if not exists', async () => {
      mockRequest.headers.authorization = 'Bearer valid-token';
      mockFirebaseStrategy.validateToken.mockResolvedValue(mockFirebaseUser);
      mockPrismaService.user.upsert.mockResolvedValue(mockDbUser);

      await guard.canActivate(mockExecutionContext);

      expect(mockPrismaService.user.upsert).toHaveBeenCalledWith({
        where: { firebaseUid: 'firebase-uid-123' },
        update: {
          email: 'test@example.com',
          displayName: 'Test User',
        },
        create: {
          firebaseUid: 'firebase-uid-123',
          email: 'test@example.com',
          displayName: 'Test User',
        },
      });
    });

    it('should update user in database if exists', async () => {
      mockRequest.headers.authorization = 'Bearer valid-token';
      const updatedFirebaseUser = {
        ...mockFirebaseUser,
        email: 'updated@example.com',
        displayName: 'Updated User',
      };
      mockFirebaseStrategy.validateToken.mockResolvedValue(updatedFirebaseUser);
      const existingUser = {
        ...mockDbUser,
        email: 'old@example.com',
      };
      mockPrismaService.user.upsert.mockResolvedValue(existingUser);

      await guard.canActivate(mockExecutionContext);

      expect(mockPrismaService.user.upsert).toHaveBeenCalledWith({
        where: { firebaseUid: 'firebase-uid-123' },
        update: {
          email: 'updated@example.com',
          displayName: 'Updated User',
        },
        create: {
          firebaseUid: 'firebase-uid-123',
          email: 'updated@example.com',
          displayName: 'Updated User',
        },
      });
    });

    it('should attach user to request object', async () => {
      mockRequest.headers.authorization = 'Bearer valid-token';
      mockFirebaseStrategy.validateToken.mockResolvedValue(mockFirebaseUser);
      mockPrismaService.user.upsert.mockResolvedValue(mockDbUser);

      await guard.canActivate(mockExecutionContext);

      expect(mockRequest.user).toBeDefined();
      expect(mockRequest.user).toHaveProperty('id');
      expect(mockRequest.user).toHaveProperty('firebaseUid');
      expect(mockRequest.user).toHaveProperty('email');
      expect(mockRequest.user).toHaveProperty('displayName');
      expect(mockRequest.user).toHaveProperty('defaultOrgId');
    });

    it('should throw UnauthorizedException when token is invalid', async () => {
      mockRequest.headers.authorization = 'Bearer invalid-token';
      mockFirebaseStrategy.validateToken.mockRejectedValue(
        new UnauthorizedException('Invalid or expired token'),
      );

      await expect(guard.canActivate(mockExecutionContext)).rejects.toThrow(
        UnauthorizedException,
      );
      expect(mockPrismaService.user.upsert).not.toHaveBeenCalled();
    });

    it('should throw UnauthorizedException when token is expired', async () => {
      mockRequest.headers.authorization = 'Bearer expired-token';
      mockFirebaseStrategy.validateToken.mockRejectedValue(
        new UnauthorizedException('Invalid or expired token'),
      );

      await expect(guard.canActivate(mockExecutionContext)).rejects.toThrow(
        UnauthorizedException,
      );
    });

    it('should extract token correctly from Bearer header', async () => {
      mockRequest.headers.authorization = 'Bearer my-token-123';
      mockFirebaseStrategy.validateToken.mockResolvedValue(mockFirebaseUser);
      mockPrismaService.user.upsert.mockResolvedValue(mockDbUser);

      await guard.canActivate(mockExecutionContext);

      expect(mockFirebaseStrategy.validateToken).toHaveBeenCalledWith('my-token-123');
    });
  });
});

