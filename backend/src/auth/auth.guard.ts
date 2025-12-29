import {
  Injectable,
  CanActivate,
  ExecutionContext,
  UnauthorizedException,
} from '@nestjs/common';
import { FirebaseStrategy } from './firebase.strategy';
import { PrismaService } from '../prisma/prisma.service';
import { CurrentUserData } from '../common/decorators/current-user.decorator';

@Injectable()
export class AuthGuard implements CanActivate {
  constructor(
    private firebaseStrategy: FirebaseStrategy,
    private prisma: PrismaService,
  ) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest();
    const authHeader = request.headers.authorization;

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      throw new UnauthorizedException('Missing or invalid authorization header');
    }

    const token = authHeader.substring(7);
    const firebaseUser = await this.firebaseStrategy.validateToken(token);

    // Get or create user in database
    const user = await this.prisma.user.upsert({
      where: { firebaseUid: firebaseUser.firebaseUid },
      update: {
        email: firebaseUser.email,
        displayName: firebaseUser.displayName,
      },
      create: {
        firebaseUid: firebaseUser.firebaseUid,
        email: firebaseUser.email,
        displayName: firebaseUser.displayName,
      },
    });

    // Attach user to request
    request.user = {
      id: user.id,
      firebaseUid: user.firebaseUid,
      email: user.email,
      displayName: user.displayName,
      defaultOrgId: user.defaultOrgId,
    } as CurrentUserData;

    return true;
  }
}

