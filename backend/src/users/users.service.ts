import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { CreateUserDto } from './dto/create-user.dto';

@Injectable()
export class UsersService {
  constructor(private prisma: PrismaService) {}

  async syncUser(createUserDto: CreateUserDto) {
    const user = await this.prisma.user.upsert({
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

    return user;
  }

  async findById(id: string) {
    const user = await this.prisma.user.findUnique({
      where: { id },
      select: {
        id: true,
        firebaseUid: true,
        email: true,
        displayName: true,
        defaultOrgId: true,
        createdAt: true,
      },
    });

    if (!user) {
      throw new NotFoundException('User not found');
    }

    return user;
  }

  async findByFirebaseUid(firebaseUid: string) {
    const user = await this.prisma.user.findUnique({
      where: { firebaseUid },
      select: {
        id: true,
        firebaseUid: true,
        email: true,
        displayName: true,
        defaultOrgId: true,
        createdAt: true,
      },
    });

    if (!user) {
      throw new NotFoundException('User not found');
    }

    return user;
  }
}

