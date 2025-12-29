import { Test, TestingModule } from '@nestjs/testing';
import { OrganizationsService } from './organizations.service';
import { PrismaService } from '../prisma/prisma.service';
import { NotFoundException, ForbiddenException, BadRequestException } from '@nestjs/common';

describe('OrganizationsService', () => {
  let service: OrganizationsService;
  let prisma: PrismaService;

  const mockPrismaService = {
    organization: {
      findMany: jest.fn(),
      findUnique: jest.fn(),
      findFirst: jest.fn(),
      count: jest.fn(),
      create: jest.fn(),
      update: jest.fn(),
      delete: jest.fn(),
    },
    user: {
      findUnique: jest.fn(),
      update: jest.fn(),
    },
  };

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        OrganizationsService,
        {
          provide: PrismaService,
          useValue: mockPrismaService,
        },
      ],
    }).compile();

    service = module.get<OrganizationsService>(OrganizationsService);
    prisma = module.get<PrismaService>(PrismaService);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });

  describe('findAll', () => {
    it('should return all organizations for a user', async () => {
      const userId = 'user-123';
      const mockOrgs = [
        {
          id: 'org-1',
          name: 'Org 1',
          orgId: 'org-id-1',
          isDefault: true,
          createdAt: new Date(),
          updatedAt: new Date(),
        },
      ];

      mockPrismaService.organization.findMany.mockResolvedValue(mockOrgs);

      const result = await service.findAll(userId);

      expect(result).toEqual(mockOrgs);
      expect(mockPrismaService.organization.findMany).toHaveBeenCalledWith({
        where: { userId },
        select: expect.any(Object),
        orderBy: { createdAt: 'asc' },
      });
    });
  });

  describe('findOne', () => {
    it('should return organization if found and belongs to user', async () => {
      const orgId = 'org-id-1';
      const userId = 'user-123';
      const mockOrg = {
        id: 'org-1',
        name: 'Org 1',
        orgId: 'org-id-1',
        userId: 'user-123',
        isDefault: true,
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      mockPrismaService.organization.findUnique.mockResolvedValue(mockOrg);

      const result = await service.findOne(orgId, userId);

      expect(result).toBeDefined();
      expect(result.orgId).toBe(orgId);
    });

    it('should throw NotFoundException if organization not found', async () => {
      mockPrismaService.organization.findUnique.mockResolvedValue(null);

      await expect(service.findOne('non-existent', 'user-123')).rejects.toThrow(
        NotFoundException,
      );
    });

    it('should throw ForbiddenException if organization belongs to different user', async () => {
      const mockOrg = {
        id: 'org-1',
        userId: 'different-user',
        orgId: 'org-id-1',
      };

      mockPrismaService.organization.findUnique.mockResolvedValue(mockOrg);

      await expect(service.findOne('org-id-1', 'user-123')).rejects.toThrow(
        ForbiddenException,
      );
    });
  });

  describe('create', () => {
    it('should create organization and set as default if first one', async () => {
      const userId = 'user-123';
      const createDto = { name: 'New Org' };

      mockPrismaService.organization.count.mockResolvedValue(0);
      mockPrismaService.organization.create.mockResolvedValue({
        id: 'org-1',
        name: 'New Org',
        orgId: 'org-id-1',
        isDefault: true,
        createdAt: new Date(),
        updatedAt: new Date(),
      });

      const result = await service.create(userId, createDto);

      expect(result.isDefault).toBe(true);
      expect(mockPrismaService.user.update).toHaveBeenCalled();
    });

    it('should create organization without setting as default if not first', async () => {
      const userId = 'user-123';
      const createDto = { name: 'Second Org' };

      mockPrismaService.organization.count.mockResolvedValue(1);
      mockPrismaService.organization.create.mockResolvedValue({
        id: 'org-2',
        name: 'Second Org',
        orgId: 'org-id-2',
        isDefault: false,
        createdAt: new Date(),
        updatedAt: new Date(),
      });

      const result = await service.create(userId, createDto);

      expect(result.isDefault).toBe(false);
      expect(mockPrismaService.user.update).not.toHaveBeenCalled();
    });
  });

  describe('remove', () => {
    it('should throw BadRequestException if only organization', async () => {
      const orgId = 'org-id-1';
      const userId = 'user-123';

      mockPrismaService.organization.findUnique.mockResolvedValue({
        id: 'org-1',
        userId,
        awsAccounts: [],
      });
      mockPrismaService.organization.count.mockResolvedValue(1);

      await expect(service.remove(orgId, userId)).rejects.toThrow(BadRequestException);
    });

    it('should throw BadRequestException if has AWS accounts', async () => {
      const orgId = 'org-id-1';
      const userId = 'user-123';

      mockPrismaService.organization.findUnique.mockResolvedValue({
        id: 'org-1',
        userId,
        awsAccounts: [{ id: 'account-1' }],
      });
      mockPrismaService.organization.count.mockResolvedValue(2);

      await expect(service.remove(orgId, userId)).rejects.toThrow(BadRequestException);
    });
  });
});

