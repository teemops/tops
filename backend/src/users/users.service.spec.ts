import { Test, TestingModule } from '@nestjs/testing';
import { UsersService } from './users.service';
import { PrismaService } from '../prisma/prisma.service';
import { NotFoundException } from '@nestjs/common';

describe('UsersService', () => {
  let service: UsersService;
  let prisma: PrismaService;

  const mockPrismaService = {
    user: {
      upsert: jest.fn(),
      findUnique: jest.fn(),
    },
  };

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        UsersService,
        {
          provide: PrismaService,
          useValue: mockPrismaService,
        },
      ],
    }).compile();

    service = module.get<UsersService>(UsersService);
    prisma = module.get<PrismaService>(PrismaService);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });

  describe('syncUser', () => {
    it('should create a new user if not exists', async () => {
      const createUserDto = {
        firebaseUid: 'firebase-uid-123',
        email: 'test@example.com',
        displayName: 'Test User',
      };

      const mockUser = {
        id: 'user-id-123',
        ...createUserDto,
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      mockPrismaService.user.upsert.mockResolvedValue(mockUser);

      const result = await service.syncUser(createUserDto);

      expect(result).toEqual(mockUser);
      expect(mockPrismaService.user.upsert).toHaveBeenCalledWith({
        where: { firebaseUid: createUserDto.firebaseUid },
        update: {
          email: createUserDto.email,
          displayName: createUserDto.displayName,
        },
        create: {
          firebaseUid: createUserDto.firebaseUid,
          email: createUserDto.email,
          displayName: createUserDto.displayName,
        },
      });
    });
  });

  describe('findById', () => {
    it('should return user if found', async () => {
      const userId = 'user-id-123';
      const mockUser = {
        id: userId,
        firebaseUid: 'firebase-uid-123',
        email: 'test@example.com',
        displayName: 'Test User',
        defaultOrgId: null,
        createdAt: new Date(),
      };

      mockPrismaService.user.findUnique.mockResolvedValue(mockUser);

      const result = await service.findById(userId);

      expect(result).toEqual(mockUser);
      expect(mockPrismaService.user.findUnique).toHaveBeenCalledWith({
        where: { id: userId },
        select: expect.any(Object),
      });
    });

    it('should throw NotFoundException if user not found', async () => {
      mockPrismaService.user.findUnique.mockResolvedValue(null);

      await expect(service.findById('non-existent-id')).rejects.toThrow(
        NotFoundException,
      );
    });
  });
});

