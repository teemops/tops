import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { FirebaseStrategy } from './firebase.strategy';
import { AuthGuard } from './auth.guard';
import { PrismaModule } from '../prisma/prisma.module';

@Module({
  imports: [ConfigModule, PrismaModule],
  providers: [FirebaseStrategy, AuthGuard],
  exports: [FirebaseStrategy, AuthGuard],
})
export class AuthModule {}

