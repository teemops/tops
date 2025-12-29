import {
  Injectable,
  NotFoundException,
  ForbiddenException,
  BadRequestException,
} from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { CreateOrganizationDto } from './dto/create-organization.dto';
import { UpdateOrganizationDto } from './dto/update-organization.dto';

@Injectable()
export class OrganizationsService {
  constructor(private prisma: PrismaService) {}

  async findAll(userId: string) {
    return this.prisma.organization.findMany({
      where: { userId },
      select: {
        id: true,
        name: true,
        orgId: true,
        isDefault: true,
        createdAt: true,
        updatedAt: true,
      },
      orderBy: { createdAt: 'asc' },
    });
  }

  async findOne(orgId: string, userId: string) {
    const organization = await this.prisma.organization.findUnique({
      where: { orgId },
    });

    if (!organization) {
      throw new NotFoundException('Organization not found');
    }

    if (organization.userId !== userId) {
      throw new ForbiddenException('You do not have access to this organization');
    }

    return {
      id: organization.id,
      name: organization.name,
      orgId: organization.orgId,
      isDefault: organization.isDefault,
      createdAt: organization.createdAt,
      updatedAt: organization.updatedAt,
    };
  }

  async create(userId: string, createOrganizationDto: CreateOrganizationDto) {
    // Check if user already has organizations
    const existingOrgs = await this.prisma.organization.count({
      where: { userId },
    });

    const isDefault = existingOrgs === 0;

    const organization = await this.prisma.organization.create({
      data: {
        name: createOrganizationDto.name,
        userId,
        isDefault,
      },
      select: {
        id: true,
        name: true,
        orgId: true,
        isDefault: true,
        createdAt: true,
        updatedAt: true,
      },
    });

    // If this is the first organization, update user's defaultOrgId
    if (isDefault) {
      await this.prisma.user.update({
        where: { id: userId },
        data: { defaultOrgId: organization.orgId },
      });
    }

    return organization;
  }

  async update(orgId: string, userId: string, updateOrganizationDto: UpdateOrganizationDto) {
    // Verify organization exists and belongs to user
    const organization = await this.prisma.organization.findUnique({
      where: { orgId },
    });

    if (!organization) {
      throw new NotFoundException('Organization not found');
    }

    if (organization.userId !== userId) {
      throw new ForbiddenException('You do not have access to this organization');
    }

    return this.prisma.organization.update({
      where: { orgId },
      data: updateOrganizationDto,
      select: {
        id: true,
        name: true,
        orgId: true,
        isDefault: true,
        createdAt: true,
        updatedAt: true,
      },
    });
  }

  async remove(orgId: string, userId: string) {
    // Verify organization exists and belongs to user
    const organization = await this.prisma.organization.findUnique({
      where: { orgId },
      include: {
        awsAccounts: true,
      },
    });

    if (!organization) {
      throw new NotFoundException('Organization not found');
    }

    if (organization.userId !== userId) {
      throw new ForbiddenException('You do not have access to this organization');
    }

    // Check if it's the only organization
    const orgCount = await this.prisma.organization.count({
      where: { userId },
    });

    if (orgCount === 1) {
      throw new BadRequestException('Cannot delete the only organization');
    }

    // Check if it has AWS accounts
    if (organization.awsAccounts.length > 0) {
      throw new BadRequestException(
        'Cannot delete organization with AWS accounts. Please remove all AWS accounts first.',
      );
    }

    // If it's the default, set another organization as default
    if (organization.isDefault) {
      const anotherOrg = await this.prisma.organization.findFirst({
        where: {
          userId,
          orgId: { not: orgId },
        },
      });

      if (anotherOrg) {
        await this.prisma.organization.update({
          where: { orgId: anotherOrg.orgId },
          data: { isDefault: true },
        });

        await this.prisma.user.update({
          where: { id: userId },
          data: { defaultOrgId: anotherOrg.orgId },
        });
      }
    }

    await this.prisma.organization.delete({
      where: { orgId },
    });

    return { success: true, message: 'Organization deleted successfully' };
  }

  async getCurrent(userId: string) {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      include: {
        organizations: {
          where: { isDefault: true },
          take: 1,
        },
      },
    });

    if (!user) {
      throw new NotFoundException('User not found');
    }

    if (user.organizations.length === 0) {
      // Create default organization if none exists
      return this.create(userId, { name: 'My Organization' });
    }

    const org = user.organizations[0];
    return {
      id: org.id,
      name: org.name,
      orgId: org.orgId,
      isDefault: org.isDefault,
    };
  }
}

