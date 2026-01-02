import { Test, TestingModule } from '@nestjs/testing';
import { INestApplication, ValidationPipe } from '@nestjs/common';
import * as request from 'supertest';
import { AppModule } from '../src/app.module';
import { PrismaService } from '../src/prisma/prisma.service';
import { FirebaseStrategy } from '../src/auth/firebase.strategy';

// Mock FirebaseStrategy for E2E tests
jest.mock('../src/auth/firebase.strategy');

describe('UsersController (e2e)', () => {
  let app: INestApplication;
  let prisma: PrismaService;
  let firebaseStrategy: FirebaseStrategy;
  let validToken: string;
  let invalidToken: string;
  let testUser: any;

  beforeAll(async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideProvider(FirebaseStrategy)
      .useValue({
        validateToken: jest.fn(),
      })
      .compile();

    app = moduleFixture.createNestApplication();
    prisma = moduleFixture.get<PrismaService>(PrismaService);
    firebaseStrategy = moduleFixture.get<FirebaseStrategy>(FirebaseStrategy);

    // Apply global validation pipe like in main.ts
    app.useGlobalPipes(
      new ValidationPipe({
        whitelist: true,
        forbidNonWhitelisted: true,
        transform: true,
      }),
    );

    app.setGlobalPrefix('api');
    await app.init();

    // Create test user in database
    testUser = await prisma.user.upsert({
      where: { firebaseUid: 'test-firebase-uid' },
      update: {},
      create: {
        firebaseUid: 'test-firebase-uid',
        email: 'test@example.com',
        displayName: 'Test User',
      },
    });

    validToken = 'valid-firebase-token';
    invalidToken = 'invalid-firebase-token';

    // Setup mock for valid token
    (firebaseStrategy.validateToken as jest.Mock).mockImplementation(
      (token: string) => {
        if (token === validToken) {
          return Promise.resolve({
            id: 'test-firebase-uid',
            firebaseUid: 'test-firebase-uid',
            email: 'test@example.com',
            displayName: 'Test User',
          });
        }
        throw new Error('Invalid token');
      },
    );
  });

  afterAll(async () => {
    // Clean up test data
    await prisma.user.deleteMany({
      where: {
        firebaseUid: {
          in: ['test-firebase-uid', 'new-firebase-uid'],
        },
      },
    });
    await app.close();
  });

  describe('POST /api/users/sync', () => {
    it('should create user with valid Firebase data', async () => {
      (firebaseStrategy.validateToken as jest.Mock).mockResolvedValueOnce({
        id: 'new-firebase-uid',
        firebaseUid: 'new-firebase-uid',
        email: 'newuser@example.com',
        displayName: 'New User',
      });

      const response = await request(app.getHttpServer())
        .post('/api/users/sync')
        .set('Authorization', `Bearer ${validToken}`)
        .send({
          firebaseUid: 'new-firebase-uid',
          email: 'newuser@example.com',
          displayName: 'New User',
        })
        .expect(200);

      expect(response.body).toHaveProperty('id');
      expect(response.body.email).toBe('newuser@example.com');
      expect(response.body.firebaseUid).toBe('new-firebase-uid');

      // Clean up
      await prisma.user.delete({
        where: { firebaseUid: 'new-firebase-uid' },
      });
    });

    it('should update existing user', async () => {
      (firebaseStrategy.validateToken as jest.Mock).mockResolvedValueOnce({
        id: testUser.firebaseUid,
        firebaseUid: testUser.firebaseUid,
        email: 'updated@example.com',
        displayName: 'Updated User',
      });

      const response = await request(app.getHttpServer())
        .post('/api/users/sync')
        .set('Authorization', `Bearer ${validToken}`)
        .send({
          firebaseUid: testUser.firebaseUid,
          email: 'updated@example.com',
          displayName: 'Updated User',
        })
        .expect(200);

      expect(response.body.email).toBe('updated@example.com');
      expect(response.body.displayName).toBe('Updated User');
    });

    it('should return 401 without authentication', async () => {
      await request(app.getHttpServer())
        .post('/api/users/sync')
        .send({
          firebaseUid: 'test-uid',
          email: 'test@example.com',
        })
        .expect(401);
    });

    it('should return 401 with invalid token', async () => {
      (firebaseStrategy.validateToken as jest.Mock).mockRejectedValueOnce(
        new Error('Invalid token'),
      );

      await request(app.getHttpServer())
        .post('/api/users/sync')
        .set('Authorization', `Bearer ${invalidToken}`)
        .send({
          firebaseUid: 'test-uid',
          email: 'test@example.com',
        })
        .expect(401);
    });

    it('should validate request body', async () => {
      await request(app.getHttpServer())
        .post('/api/users/sync')
        .set('Authorization', `Bearer ${validToken}`)
        .send({
          // Missing required fields
        })
        .expect(400);
    });
  });

  describe('GET /api/users/me', () => {
    it('should return current user with valid token', async () => {
      const response = await request(app.getHttpServer())
        .get('/api/users/me')
        .set('Authorization', `Bearer ${validToken}`)
        .expect(200);

      expect(response.body).toHaveProperty('id');
      expect(response.body).toHaveProperty('email');
      expect(response.body).toHaveProperty('firebaseUid');
      expect(response.body.email).toBe('test@example.com');
    });

    it('should return 401 without authentication', async () => {
      await request(app.getHttpServer())
        .get('/api/users/me')
        .expect(401);
    });

    it('should return 401 with invalid token', async () => {
      (firebaseStrategy.validateToken as jest.Mock).mockRejectedValueOnce(
        new Error('Invalid token'),
      );

      await request(app.getHttpServer())
        .get('/api/users/me')
        .set('Authorization', `Bearer ${invalidToken}`)
        .expect(401);
    });

    it('should return 401 with expired token', async () => {
      const expiredError = new Error('Token expired');
      expiredError.name = 'TokenExpiredError';
      (firebaseStrategy.validateToken as jest.Mock).mockRejectedValueOnce(
        expiredError,
      );

      await request(app.getHttpServer())
        .get('/api/users/me')
        .set('Authorization', 'Bearer expired-token')
        .expect(401);
    });

    it('should return 401 with malformed authorization header', async () => {
      await request(app.getHttpServer())
        .get('/api/users/me')
        .set('Authorization', 'InvalidFormat token')
        .expect(401);
    });

    it('should return 401 with missing Bearer prefix', async () => {
      await request(app.getHttpServer())
        .get('/api/users/me')
        .set('Authorization', 'just-a-token')
        .expect(401);
    });
  });
});

