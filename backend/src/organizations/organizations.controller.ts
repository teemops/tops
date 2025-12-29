import {
  Controller,
  Get,
  Post,
  Put,
  Delete,
  Body,
  Param,
  UseGuards,
  HttpCode,
  HttpStatus,
} from '@nestjs/common';
import {
  ApiTags,
  ApiOperation,
  ApiBearerAuth,
  ApiParam,
  ApiResponse,
} from '@nestjs/swagger';
import { OrganizationsService } from './organizations.service';
import { CreateOrganizationDto } from './dto/create-organization.dto';
import { UpdateOrganizationDto } from './dto/update-organization.dto';
import { AuthGuard } from '../auth/auth.guard';
import { CurrentUser } from '../common/decorators/current-user.decorator';
import type { CurrentUserData } from '../common/decorators/current-user.decorator';

@ApiTags('Organizations')
@Controller('organizations')
@UseGuards(AuthGuard)
@ApiBearerAuth()
export class OrganizationsController {
  constructor(private readonly organizationsService: OrganizationsService) {}

  @Get()
  @ApiOperation({ summary: 'List all organizations for current user' })
  @ApiResponse({ status: 200, description: 'List of organizations' })
  async getOrganizations(@CurrentUser() user: CurrentUserData) {
    return this.organizationsService.findAll(user.id);
  }

  @Get('current')
  @ApiOperation({ summary: 'Get current/default organization' })
  @ApiResponse({ status: 200, description: 'Current organization' })
  async getCurrentOrganization(@CurrentUser() user: CurrentUserData) {
    return this.organizationsService.getCurrent(user.id);
  }

  @Get(':orgId')
  @ApiOperation({ summary: 'Get organization by ID' })
  @ApiParam({ name: 'orgId', description: 'Organization ID (orgId, not id)' })
  @ApiResponse({ status: 200, description: 'Organization details' })
  @ApiResponse({ status: 404, description: 'Organization not found' })
  @ApiResponse({ status: 403, description: 'Access denied' })
  async getOrganization(
    @Param('orgId') orgId: string,
    @CurrentUser() user: CurrentUserData,
  ) {
    return this.organizationsService.findOne(orgId, user.id);
  }

  @Post()
  @HttpCode(HttpStatus.CREATED)
  @ApiOperation({ summary: 'Create a new organization' })
  @ApiResponse({ status: 201, description: 'Organization created' })
  async createOrganization(
    @Body() createOrganizationDto: CreateOrganizationDto,
    @CurrentUser() user: CurrentUserData,
  ) {
    return this.organizationsService.create(user.id, createOrganizationDto);
  }

  @Put(':orgId')
  @ApiOperation({ summary: 'Update organization' })
  @ApiParam({ name: 'orgId', description: 'Organization ID (orgId, not id)' })
  @ApiResponse({ status: 200, description: 'Organization updated' })
  @ApiResponse({ status: 404, description: 'Organization not found' })
  @ApiResponse({ status: 403, description: 'Access denied' })
  async updateOrganization(
    @Param('orgId') orgId: string,
    @Body() updateOrganizationDto: UpdateOrganizationDto,
    @CurrentUser() user: CurrentUserData,
  ) {
    return this.organizationsService.update(orgId, user.id, updateOrganizationDto);
  }

  @Delete(':orgId')
  @HttpCode(HttpStatus.OK)
  @ApiOperation({ summary: 'Delete organization' })
  @ApiParam({ name: 'orgId', description: 'Organization ID (orgId, not id)' })
  @ApiResponse({ status: 200, description: 'Organization deleted' })
  @ApiResponse({ status: 404, description: 'Organization not found' })
  @ApiResponse({ status: 403, description: 'Access denied' })
  @ApiResponse({ status: 400, description: 'Cannot delete (has accounts or is only org)' })
  async deleteOrganization(
    @Param('orgId') orgId: string,
    @CurrentUser() user: CurrentUserData,
  ) {
    return this.organizationsService.remove(orgId, user.id);
  }
}

